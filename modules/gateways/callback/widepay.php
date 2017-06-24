<?php

/**
 * Dependencias
 */
use WHMCS\Database\Capsule;
require_once('../widepay/WidePay.php');
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Pegando as váriaveis de configuração
$gatewayParams = getGatewayVariables('widepay');
// Verifica se o modulo está ativo
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Pegando informações sobre a notificação no servidor Wide Pay
$wp = new WidePay($gatewayParams['walletNumber'], $gatewayParams['walletToken']);
$widePay = $wp->api('recebimentos/cobrancas/notificacao', array(
    'id' => $_POST['notificacao']
));

//Registrando notificação
logTransaction($gatewayParams['name'], $_POST, '(Notificação Wide Pay) Status: ' . $widePay->cobranca['status'] .' - Success:'. $widePay->success . ' Fatura: ' . $widePay->cobranca['referencia']);

//Caso Sucesso
if ($widePay->success) {

    //Pegando fatura no banco de dados
    $postData = array(
        'invoiceid' => $widePay->cobranca['referencia'],
    );
    $adminUsername = $gatewayParams['adminWHMCSLogin'];
    $results = localAPI('GetInvoice', $postData, $adminUsername);

    //Verifica se a fatura já foi liquidada no sistema WHMCS
    if($results['status'] == 'Paid'){
        //Informando pelo log
        logTransaction($gatewayParams['name'], $_POST, '(ERRO de Notificação Wide Pay) Esta fatura já foi liquidada no sistema: WHMCS. Fatura: ' . $widePay->cobranca['referencia']);
        //Finalizando verificação
        exit("Esta fatura já foi liquidada no sistema: WHMCS.");
    }

    //Caso a notificação for de baixa ou recebido.
    if ($widePay->cobranca['status'] == 'Recebido' || $widePay->cobranca['status'] ==  "Baixado"){

        $addTransactionCommand                  = "addtransaction";
        $addTransactionValues['userid']         = $results['userid'];
        $addTransactionValues['invoiceid']      = $widePay->cobranca['referencia'];
        $addTransactionValues['description']    = 'Notificação valor recebido WidePay';
        $addTransactionValues['amountin']       = ($widePay->cobranca['status'] ==  "Baixado")? $widePay->cobranca['valor'] : $widePay->cobranca['recebido'] ;
        $addTransactionValues['fees']           = $widePay->cobranca['tarifa'];
        $addTransactionValues['paymentmethod']  = 'widepay';
        $addTransactionValues['transid']        = $widePay->cobranca['id'];
        $addTransactionValues['date']           = date('d/m/Y');
        $addtransresults = localAPI($addTransactionCommand, $addTransactionValues, $adminWHMCS );

        if($addtransresults['result'] == "error"){// Caso ocorra um erro ao adicionar recebimento
            //Finalizando verificação
            exit('Erro WHMCS -> ' . $addtransresults['message']);
        }else{

            // Iremos verificar se a fatura foi liquidada, quando é consedido desconto na configuração do Wide Pay e o valor pago é menor que a fatura, a mesma não é liquidada.
            //Pegando fatura no banco de dados
            $postData = array(
                'invoiceid' => $widePay->cobranca['referencia'],
            );
            $adminUsername = $gatewayParams['adminWHMCSLogin'];
            $results = localAPI('GetInvoice', $postData, $adminUsername);
            //Verifica se a fatura foi liquidada no sistema WHMCS
            if($results['status'] == 'Unpaid'){
                //Pega a requisição
                $widepayInvoice = Capsule::table('mod_widepay')
                    ->where('invoice', $widePay->cobranca['referencia'])
                    ->orderBy('id', 'desc')
                    ->first();

                if((float)$widePay->cobranca['valor'] >= (float)$widepayInvoice->total){
                    $postData["invoiceid"] = (int)$widePay->cobranca['referencia'];
                    $postData["status"]    = 'Paid';
                    $postData["datepaid"] = ($widePay->cobranca['status'] ==  "Baixado")? date('Y-m-d') : $widePay->cobranca['recebimento'];
                    $results = localAPI('updateinvoice', $postData, $adminWHMCS);
                }
            }

            //Finalizando verificação
            exit("Notificação recebida com sucesso no sistema WHMCS.");
        }


    }else{// O status não é de baixa ou valor recebido
        //Finalizando verificação
        exit("A notificação do tipo: " .$widePay->cobranca['status'] . ", não é suportada na plataforma WHMCS.");
    }
} else { // Erro retornado do Wide Pay
    //Finalizando verificação
    exit('ERRO!<br><br>' . $widePay->error);
}


