#  Módulo de Integração para WHMCS 
Módulo desenvolvido para integração entre o WHMCS e Wide Pay.  
Com o modulo é possível gerar boletos para pagamento e liquidação automática pelo Wide Pay após o recebimento.

# Instalação e Configuração
O modulo de integração é constituído por 3 arquivos, encaminhe os arquivos para as respectivas pastas do sistema WHMCS.  
modules/gateways/**callback/widepay.php**  
modules/gateways/**widepay/WidePay.php**  
modules/gateways/**widepay.php**

Após enviado acesse a administração. Para ativar o Wide Pay clique no botão: **Setup -> Payments -> 
Payment Gateways -> All Payment Gateways -> "Wide Pay".**

Para configurar acesse o menu: **Setup -> Payments -> 
Payment Gateways -> Manage Existing Gateways -> "Wide Pay"**

Para integração recolha o **ID e Token da carteira** na administração de sua conta Wide Pay. [https://www.widepay.com/conta/configuracoes/carteiras](https://www.widepay.com/conta/configuracoes/carteiras)