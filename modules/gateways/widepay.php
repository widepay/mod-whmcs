<?php


/**
 * Dependencias
 */
use WHMCS\Database\Capsule;
require_once('widepay/WidePay.php');


if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Plugin WHMCS Plugin
 * @return array
 */
function widepay_MetaData()
{
    return array(
        'DisplayName' => 'Wide Pay',
        'APIVersion' => '1.1',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * WHMCS Gateway Config
 * @return array
 */
function widepay_config()
{
    return array(
        // Nome do Gateway Plugin
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Wide Pay',
        ),

        // Configuração do campo 'ID da Carteira Wide Pay'
        'walletNumber' => array(
            'FriendlyName' => 'ID da Carteira Wide Pay',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => '<i style="color:#ff1c29;">* (Obrigatório)</i>',
        ),

        // Configuração do campo 'Token da Carteira Wide Pay'
        'walletToken' => array(
            'FriendlyName' => 'Token da Carteira Wide Pay',
            'Type' => 'text',
            'Size' => '40',
            'Description' => '<i style="color:#ff1c29;">* (Obrigatório)</i>',
        ),

        // Configuração do campo 'Taxa de Variação'
        'tax' => array(
            'FriendlyName' => 'Taxa de Variação',
            'Type' => 'text',
            'Size' => '10',
            'Description' => '<br>O valor final da fatura será alterado de acordo com este campo.<br>Coloque 0 para não alterar.',
        ),

        // Configuração do campo 'Tipo da Taxa de Variação'
        'taxType' => array(
            'FriendlyName' => 'Tipo da Taxa de Variação',
            'Type' => 'dropdown',
            'Options' => array(
                '1' => 'Acrécimo em %',
                '2' => 'Acrécimo valor fixo',
                '3' => 'Desconto em %',
                '4' => 'Desconto valor fixo',
            ),
            'Description' => '<br>A Taxa de Variação será aplicada de acordo com este campo.',
        ),

        // Configuração do campo 'Acréscimo de Dias no Vencimento'
        'plusDateDue' => array(
            'FriendlyName' => 'Acréscimo de Dias no Vencimento',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '2',
            'Description' => '<br>Configure aqui a quantidade de dias corridos para o vencimento após a geração da fatura.',
        ),

        // Configuração do campo 'Permitir que Wide Pay envie e-mail para Clientes Finais'
        'allowWidePayEmail' => array(
            'FriendlyName' => 'Permitir que Wide Pay envie e-mails',
            'Type' => 'yesno',
            'Default' => '0',
            'Description' => 'Com a opção habilitada, o Wide Pay enviará e-mails referente as faturas ao cliente final.',
        ),

        // Configuração do campo 'Configuração de Multa'
        'fine' => array(
            'FriendlyName' => 'Configuração de Multa',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Configuração em porcentagem.Exemplo: 0,5',
        ),

        // Configuração do campo 'Configuração de Juros'
        'interest' => array(
            'FriendlyName' => 'Configuração de Juros',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Configuração em porcentagem.Exemplo: 0,5',
        ),

        // Configuração do campo 'Login Admin WHMCS'
        'adminWHMCSLogin' => array(
            'FriendlyName' => 'Login Admin WHMCS',
            'Type' => 'text',
            'Size' => '40',
            'Description' => '<i style="color:#ff1c29;">* (Obrigatório)</i> <br>Coloque aqui o usuário de login do WHMCS.',
        ),


    );
}

function widepay_link($params)
{
    // Parâmetros Wide Pay
    $widepayWalletNumber = $params['walletNumber'];
    $widepayWalletToken = $params['walletToken'];
    $widepayTax = $params['tax'];
    $widepayTaxType = (int)$params['taxType'];
    $widepayPlusDateDue = $params['plusDateDue'];
    $widepayAllowWidePayEmail = $params['allowWidePayEmail'];
    $widepayFine = $params['fine'];
    $widepayInterest = $params['interest'];

    // Parâmetros da Fatura
    $invoiceId = $params['invoiceid'];
    $invoiceDuedate = $params['dueDate'];
    $description = $params["description"];
    $amount = (double)$params['amount'];
    $credit = (double)$params['credit'];

    // Parâmetros do Cliente
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];

    // Parâmetros do Sistema
    $systemUrl = $params['systemurl'];
    $moduleName = $params['paymentmethod'];


    //+++++++++++++++++++++++++++++[Configuração de Itens Wide Pay //  Tratamento caso haja crédito na fatura ou taxa adicional ]+++++++++++++++++++++++++++++++++
    //Itens WidePay
    $widepayItens = [];

    $widepayTax = str_replace(',', '.', $widepayTax);

    //Formatação para calculo ou exibição na descrição
    $widepayTaxDouble = number_format((double)$widepayTax, 2, '.', '');
    $widepayTaxReal = number_format((double)$widepayTax, 2, ',', '');
    $widepayAmountReal = number_format((double)$amount, 2, ',', '');
    $widepayCreditReal = number_format((double)$credit, 2, ',', '');

    //Caso houver crédito na fatura será descontado do valor total e o valor total será atualizado
    if ($credit > 0) {
        $widepayItens[] = [
            'descricao' => 'Discriminação de crédito: R$' . $widepayAmountReal . ' - R$' . $widepayCreditReal . ' = R$' . number_format(round(($amount - $credit), 2), 2, ',', ''),
            'valor' => 0,
        ];
        // !!!! Caso houver crédito estamos alterando o valor total
        $widepayAmountReal = number_format((double)$amount - $credit, 2, ',', '');
        $amount = $amount - $credit;
    }


    // Configuração da taxa de variação nos Itens da fatura.
    if ((float)$widepayTax != 0) {

        if ($widepayTaxType == 1) {//Acrécimo em Porcentagem
            $widepayItens[] = [
                'descricao' => $description,
                'valor' => $amount
            ];
            $widepayItens[] = [
                'descricao' => 'Referente a taxa adicional de ' . $widepayTaxReal . '%',
                'valor' => round((((double)$widepayTaxDouble / 100) * $amount), 2)
            ];
        } elseif ($widepayTaxType == 2) {//Acrécimo valor Fixo
            $widepayItens[] = [
                'descricao' => $description,
                'valor' => $amount
            ];
            $widepayItens[] = [
                'descricao' => 'Referente a taxa adicional de R$' . $widepayTaxReal,
                'valor' => ((double)$widepayTaxDouble),
            ];
        } elseif ($widepayTaxType == 3) {//Desconto em Porcentagem
            $widepayItens[] = [
                'descricao' => 'Discriminação de desconto: ' . $widepayTaxReal . '%' . ' de R$' . $widepayAmountReal . ' = R$' . number_format((double)round((((double)$widepayTaxDouble / 100) * $amount), 2), 2, ',', ''),
                'valor' => 0
            ];
            $widepayItens[] = [
                'descricao' => $description,
                'valor' => $amount - round((((double)$widepayTaxDouble / 100) * $amount), 2)
            ];
        } elseif ($widepayTaxType == 4) {//Desconto valor Fixo
            $widepayItens[] = [
                'descricao' => 'Discriminação de desconto: R$' . $widepayAmountReal . ' - R$' . $widepayTaxReal . ' = R$' . number_format(round(($amount - $widepayTaxDouble), 2), 2, ',', ''),
                'valor' => 0
            ];
            $widepayItens[] = [
                'descricao' => $description,
                'valor' => round(($amount - $widepayTaxDouble), 2)
            ];
        }
    }

    // Caso não tenha taxa de variação será adicionado o valor da fatura neste campo. Mesmo caso haja crédito da fatura.
    if (count($widepayItens) < 2) {
        $widepayItens[] = [
            'descricao' => $description,
            'valor' => $amount
        ];
    }

    //+++++++++++++++++++++++++++++[Configuração de data de vencimento ]+++++++++++++++++++++++++++++++++


    if ($widepayPlusDateDue == null || $widepayPlusDateDue == '') {
        $widepayPlusDateDue = '0';
    }

    if ($invoiceDuedate < date('Y-m-d')) {
        $invoiceDuedate = date('Y-m-d');
    }

    $invoiceDuedate = new DateTime($invoiceDuedate);
    $invoiceDuedate->modify('+' . $widepayPlusDateDue . ' day');
    $invoiceDuedate = $invoiceDuedate->format('Y-m-d');

    //+++++++++++++++++++++++++++++[Configuração Opção de envio de email Wide Pay ]+++++++++++++++++++++++++++++++++


    if($widepayAllowWidePayEmail){
        $widepayAllowWidePayEmail = 'E-mail';
    }else{
        $widepayAllowWidePayEmail = '';
    }

    //+++++++++++++++++++++++++++++[ Processo final para mostrar fatura ]+++++++++++++++++++++++++++++++++

    //Pega fatura no banco de dados caso já gerada anteriormente.
    $widepayInvoice = widepay_getInvoice($invoiceId,$amount, $widepayFine,$widepayInterest);

    //Caso a fatura não tenha sido gerada anteriormente
    if($widepayInvoice == null){

        $wp = new WidePay($widepayWalletNumber, $widepayWalletToken);
        $widepayData = array(
            'forma' => 'Boleto',
            'referencia' => $invoiceId,
            'notificacao' => $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php',
            'vencimento' => $invoiceDuedate,
            'cliente' => $firstname . ' ' . $lastname,
            'email' => $email,
            'enviar' => $widepayAllowWidePayEmail,

            'endereco' => array(
                'rua' => $address1,
                'complemento' => $address2,
                'cep' => $postcode,
                'estado' => $state,
                'cidade' => $city
            ),

            'itens' => $widepayItens,
            'boleto' => array(
                'gerar' => 'Nao',
                'desconto' => 0,
                'multa' => $widepayFine,
                'juros' => $widepayInterest
            )
        );
        // Enviando solicitação ao Wide Pay
        $dados = $wp->api('recebimentos/cobrancas/adicionar', $widepayData);

        //Verificando sucesso no retorno
        if (!$dados->sucesso) {
            logTransaction('Wide Pay', $dados->error, 'Erro Wide Pay');
            logTransaction('Wide Pay', $dados->errors, 'Erro Wide Pay');
            return '<div class="alert alert-danger" role="alert">Wide Pay: ' . $dados->error . '</div>';
        }
        //Caso sucesso, será enviada ao banco de dados
        widepay_sendInvoice($invoiceId,$amount,$widepayFine,$widepayInterest,$invoiceDuedate,$dados->id);
        $link = $dados->link;
    }else{
        $link = 'https://widepay.com/' . $widepayInvoice->idtransaction;
    }
    //Exibindo link para pagamento
    echo "<script>window.location = '$link';</script>";
    return "<a class='btn btn-success' href='$link'>Pagar Agora com Wide Pay</a>";


}

/**
 *
 * Função responsável por pegar faturas no banco de dados
 *
 * @param $invoice
 * @param $total
 * @param $fine
 * @param $interest
 * @return mixed
 */
function widepay_getInvoice($invoice, $total, $fine, $interest){
    //Cria o banco de dados caso não exista
    widepay_check();
    //Pega a requisição
    $widepayInvoice = Capsule::table('mod_widepay')
        ->where('invoice', $invoice)
        ->where('total', $total)
        ->where('fine', $fine)
        ->where('interest', $interest)
        ->orderBy('id', 'desc')
        ->first();

    return $widepayInvoice;

}

/**
 *
 * Função responsável por gravar fatura no banco de dados.
 * @param $invoice
 * @param $total
 * @param $fine
 * @param $interest
 * @param $dueDate
 * @param $idTransaction
 * @return mixed
 */
function widepay_sendInvoice($invoice, $total, $fine, $interest, $dueDate, $idTransaction){
    //Envia ao banco de dados
    $result = Capsule::table('mod_widepay')->insert(
        [
            'idtransaction' => $idTransaction,
            'invoice' => $invoice,
            'total' => $total,
            'duedate' => $dueDate,
            'fine' => $fine,
            'interest' => $interest,
        ]
    );
    return $result;

}


/**
 *
 * Verifica se o banco de dados existe, caso não, cria a tabela.
 *
 */
function widepay_check()
{
    try {
        Capsule::table('mod_widepay')->first();
    } catch (\Exception $e) {
        try {
            Capsule::schema()->create(
                'mod_widepay',
                function ($table) {
                    /** @var \Illuminate\Database\Schema\Blueprint $table */
                    $table->increments('id');
                    $table->string('idtransaction');
                    $table->integer('invoice');
                    $table->date('duedate');
                    $table->decimal('total');
                    $table->decimal('fine');
                    $table->decimal('interest');
                    $table->timestamps();
                }
            );
        } catch (\Exception $e) {
            logTransaction('Wide Pay', "Não foi possível criar o banco de dados: {$e->getMessage()}", 'Erro Wide Pay ao criar banco de dados.');
        }
    }


}