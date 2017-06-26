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
    $widepayCustomFields = widepay_getCustomFields();
    $widepayCustomFieldsHelp = '';

    if(count($widepayCustomFields) > 0){
        $widepayCustomFieldsHelp .= 'Criamos uma lista com os campos personalizados disponíveis em seu sistema, preencha o campo com o ID referente ao CPF e CNPJ do sistema:<br><ul>';
        foreach ($widepayCustomFields as $widepayCustomField){
            $widepayCustomFieldsHelp .= '<li>ID do campo: <strong>'.$widepayCustomField->id . '</strong> - ' .$widepayCustomField->fieldname .'</li>';
        }
        $widepayCustomFieldsHelp .= '</ul>';
    }else{
        $widepayCustomFieldsHelp .= '<strong>Opa. Parece que não há campos personalizados em seu sistema. Saiba como configurar <a href="#">clicando aqui</a>.</strong>';
    }

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
            'Description' => '<br>O valor final da fatura será recalculado de acordo com este campo.<br>Coloque 0 para não alterar.',
        ),

        // Configuração do campo 'Tipo da Taxa de Variação'
        'taxType' => array(
            'FriendlyName' => 'Tipo da Taxa de Variação',
            'Type' => 'dropdown',
            'Options' => array(
                '1' => 'Acrécimo em %',
                '2' => 'Acrécimo valor fixo em R$',
                '3' => 'Desconto em %',
                '4' => 'Desconto valor fixo em R$',
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
            'Description' => 'Configuração em porcentagem. Exemplo: 2',
        ),

        // Configuração do campo 'Configuração de Juros'
        'interest' => array(
            'FriendlyName' => 'Configuração de Juros',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '',
            'Description' => 'Configuração em porcentagem. Exemplo: 2',
        ),

        // Configuração do campo 'Campo referente ao CPF e CNPJ'
        'cpfCnpj' => array(
            'FriendlyName' => 'Campo referente ao CPF e CNPJ',
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Reservado para campo personalizado do sistema WHMCS referente ao CPF e CNPJ.<br>'. $widepayCustomFieldsHelp,
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
    $widepayFine = (double) $params['fine'];
    $widepayInterest = (double) $params['interest'];
    $widepayCpfCnpjFieldId = $params['cpfCnpj'];
    $widepayCpfCnpj = ''; //Será populado mais abaixo.
    $widepayCpf = ''; //Será populado mais abaixo.
    $widepayCnpj = ''; //Será populado mais abaixo.
    $widepayPessoa = 'Física'; //Será populado mais abaixo.

    // Parâmetros da Fatura
    $invoiceId = $params['invoiceid'];
    $invoiceDuedate = $params['dueDate'];
    $description = $params["description"];
    $amount = round((double)$params['amount'], 2);
    $credit = round((double)$params['credit'], 2);

    // Parâmetros do Cliente
    $userid = $params['clientdetails']['userid'];
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

    $widepayTotal = 0; // Valor total fatura WidePay.
    $widepayTax = str_replace(',', '.', $widepayTax);

    //Formatação para calculo ou exibição na descrição
    $widepayTaxDouble = number_format((double)$widepayTax, 2, '.', '');
    $widepayTaxReal = number_format((double)$widepayTax, 2, ',', '');

    $widepayCreditReal = number_format((double)$credit, 2, ',', '');


    //Caso houver crédito na fatura será descontado do valor total e o valor total será atualizado
    if ($credit > 0) {
        $widepayItens[] = [
            'descricao' => 'Item referente ao crédito da fatura: R$' . $widepayCreditReal,
            'valor' => $credit * (-1)
        ];

        // !!!! Caso houver crédito estamos alterando o valor total
        $amount = $amount - $credit;
        $widepayTotal = $widepayTotal - $credit;
    }


    // Configuração da taxa de variação nos Itens da fatura.
    if ((float)$widepayTax != 0) {

        if ($widepayTaxType == 1) {//Acrécimo em Porcentagem
            $widepayItens[] = [
                'descricao' => $description,
                'valor' => $amount
            ];
            $widepayTotal = $widepayTotal + $amount;
            $widepayItens[] = [
                'descricao' => 'Referente a taxa adicional de ' . $widepayTaxReal . '%',
                'valor' => round((((double)$widepayTaxDouble / 100) * $amount), 2)
            ];
            $widepayTotal = $widepayTotal + round((((double)$widepayTaxDouble / 100) * $amount), 2);
        } elseif ($widepayTaxType == 2) {//Acrécimo valor Fixo
            $widepayItens[] = [
                'descricao' => $description,
                'valor' => $amount
            ];
            $widepayTotal = $widepayTotal + $amount;
            $widepayItens[] = [
                'descricao' => 'Referente a taxa adicional de R$' . $widepayTaxReal,
                'valor' => ((double)$widepayTaxDouble),
            ];
            $widepayTotal = $widepayTotal + ((double)$widepayTaxDouble);
        } elseif ($widepayTaxType == 3) {//Desconto em Porcentagem
            $widepayItens[] = [
                'descricao' => $description,
                'valor' => $amount
            ];
            $widepayItens[] = [
                'descricao' => 'Item referente ao desconto: ' . $widepayTaxReal . '%',
                'valor' => round((((double)$widepayTaxDouble / 100) * $amount), 2) * (-1)
            ];
            $widepayTotal = $widepayTotal + ($amount - round((((double)$widepayTaxDouble / 100) * $amount), 2));
        } elseif ($widepayTaxType == 4) {//Desconto valor Fixo
            $widepayItens[] = [
                'descricao' => $description,
                'valor' => $amount
            ];
            $widepayItens[] = [
                'descricao' => 'Item referente ao desconto: R$' . $widepayTaxReal,
                'valor' => $widepayTaxDouble * (-1)
            ];
            $widepayTotal = $widepayTotal + (round(($amount - $widepayTaxDouble), 2));
        }
    }

    // Caso não tenha taxa de variação será adicionado o valor da fatura neste campo. Mesmo caso haja crédito da fatura.
    if (count($widepayItens) < 2) {
        $widepayItens[] = [
            'descricao' => $description,
            'valor' => $amount
        ];
        $widepayTotal = $widepayTotal + $amount;
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


    if ($widepayAllowWidePayEmail) {
        $widepayAllowWidePayEmail = 'E-mail';
    } else {
        $widepayAllowWidePayEmail = '';
    }

    //+++++++++++++++++++++++++++++[Configuração Opção de CPF e CNPJ para Wide Pay ]+++++++++++++++++++++++++++++++++

    $widepayCpfCnpj = widepay_getCpfCnpj($userid,$widepayCpfCnpjFieldId);

    if (!is_null($widepayCpfCnpj)) {
        if(strlen($widepayCpfCnpj) > 11){
            $widepayCnpj = $widepayCpfCnpj;
            $widepayPessoa = 'Jurídica';
        }else{
            $widepayCpf = $widepayCpfCnpj;
        }
    }


    //+++++++++++++++++++++++++++++[ Processo final para mostrar fatura ]+++++++++++++++++++++++++++++++++

    //Pega fatura no banco de dados caso já gerada anteriormente.
    $widepayInvoice = widepay_getInvoice($invoiceId, $widepayTotal, $widepayTaxType, $widepayFine, $widepayInterest);

    //Caso a fatura não tenha sido gerada anteriormente
    if ($widepayInvoice == null) {

        $wp = new WidePay($widepayWalletNumber, $widepayWalletToken);
        $widepayData = array(
            'forma' => 'Boleto',
            'referencia' => $invoiceId,
            'notificacao' => $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php',
            'vencimento' => $invoiceDuedate,
            'cliente' => $firstname . ' ' . $lastname,
            'email' => $email,
            'enviar' => $widepayAllowWidePayEmail,
            'pessoa' => $widepayPessoa,
            'cpf' => $widepayCpf,
            'cnpj' => $widepayCnpj,

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
            var_dump($dados);
            $validacao = '';
            if($dados->validacao){
                logTransaction('Wide Pay', $dados->validacao, 'Erro Wide Pay');
                foreach ($dados->validacao as $item){
                    $validacao .= '- ' . $item['erro'] . '<br>';
                }
            }
            if($dados->erro)
                logTransaction('Wide Pay', $dados->erro, 'Erro Wide Pay');


            return '<div class="alert alert-danger" role="alert">Wide Pay: ' . $dados->error . '<br>'. $validacao .'</div>';
        }
        //Caso sucesso, será enviada ao banco de dados
        widepay_sendInvoice($invoiceId, $widepayTotal, $widepayTaxType, $widepayFine, $widepayInterest, $invoiceDuedate, $dados->id);
        $link = $dados->link;
    } else {
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
 * @param $type
 * @param $fine
 * @param $interest
 * @return mixed
 */
function widepay_getInvoice($invoice, $total, $type, $fine, $interest)
{
    //Cria o banco de dados caso não exista
    widepay_check();
    //Pega a requisição
    $widepayInvoice = Capsule::table('mod_widepay')
        ->where('invoice', $invoice)
        ->where('total', $total)
        ->where('fine', $fine)
        ->where('interest', $interest)
        ->where('type', $type)
        ->orderBy('id', 'desc')
        ->first();

    return $widepayInvoice;

}

/**
 *
 * Função responsável por gravar fatura no banco de dados.
 * @param $invoice
 * @param $total
 * @param $type
 * @param $fine
 * @param $interest
 * @param $dueDate
 * @param $idTransaction
 * @return mixed
 */
function widepay_sendInvoice($invoice, $total, $type, $fine, $interest, $dueDate, $idTransaction)
{
    //Envia ao banco de dados
    $result = Capsule::table('mod_widepay')->insert(
        [
            'idtransaction' => $idTransaction,
            'invoice' => $invoice,
            'total' => $total,
            'duedate' => $dueDate,
            'fine' => $fine,
            'interest' => $interest,
            'type' => $type
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
                    $table->integer('type');
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


function widepay_getCustomFields()
{
    $widepayCustomFields = Capsule::table('tblcustomfields')
        ->orderBy('id', 'asc')
        ->get();
    return $widepayCustomFields;
}

function widepay_getCpfCnpj($custumer,$fieldId)
{
    $widepayCustomField = Capsule::table('tblcustomfieldsvalues')
        ->where('fieldid',$fieldId)
        ->where('relid',$custumer)
        ->orderBy('id', 'desc')
        ->first();
    if($widepayCustomField){
        return preg_replace('/\D/', '', $widepayCustomField->value);
    }else{
        return null;
    }
}