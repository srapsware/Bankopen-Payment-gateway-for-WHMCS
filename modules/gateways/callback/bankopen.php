<?php
/**
 * WHMCS bankopen Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "bankopen" and therefore all functions
 * begin "bankopen_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://github.com/srapsware/Bankopen-Payment-gateway-for-WHMCS
 * @author Shiv Singh 
 * @copyright Copyright (c) Srapsware Technologies Private Limited 2021
 * @license https://github.com/srapsware/Bankopen-Payment-gateway-for-WHMCS/blob/main/LICENSE
 */

require_once __DIR__.'/../bankopen/layer_api.php';

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$systemUrl = $gatewayParams['systemurl'];

$testMode = $gatewayParams['testMode'];


if($testMode)
{
$APIKey = $gatewayParams['SandboxAPIKey'];
$APISecret = $gatewayParams['SandboxAPISecret'];
$remote_script = "https://sandbox-payments.open.money/layer";
$environment = 'test';

}else{

$APIKey = $gatewayParams['LiveAPIKey'];
$APISecret = $gatewayParams['LiveAPISecret'];
$remote_script = "https://payments.open.money/layer";
$environment = 'production';

}



$error = "";
$status = "";

if(!isset($_POST['layer_payment_id']) || empty($_POST['layer_payment_id'])){
	$error = "Invalid response.";    
}
try {
    $data = array(
        'layer_pay_token_id'    => $_POST['layer_pay_token_id'],
        'layer_order_amount'    => $_POST['layer_order_amount'],
        'tranid'     			=> $_POST['tranid'],
    );

    if(empty($error) && verify_hash($data,$_POST['hash'],$APIKey,$APISecret) && !empty($data['tranid'])){
        $layer_api = new LayerApi($environment,$APIKey,$APISecret);
        $payment_data = $layer_api->get_payment_details($_POST['layer_payment_id']);


        if(isset($payment_data['error'])){
            $error = "Layer: an error occurred E14".$payment_data['error'];
        }

        if(empty($error) && isset($payment_data['id']) && !empty($payment_data)){
            if($payment_data['payment_token']['id'] != $data['layer_pay_token_id']){
                $error = "Layer: received layer_pay_token_id and collected layer_pay_token_id doesnt match";
            }
            elseif($data['layer_order_amount'] != $payment_data['amount']){
                $error = "Layer: received amount and collected amount doesnt match";
            }
            else {
                switch ($payment_data['status']){
                    case 'authorized':
                    case 'captured':
                        $status = "Payment captured: Payment ID ". $payment_data['id'];
                        break;
                    case 'failed':								    
                    case 'cancelled':
                        $status = "Payment cancelled/failed: Payment ID ". $payment_data['id'];                        
                        break;
                    default:
                        $status = "Payment pending: Payment ID ". $payment_data['id'];
                        exit;
                    break;
                }
            }
        } else {
            $error = "invalid payment data received E98";
        }
    } else {
        $error = "hash validation failed";
    }

} catch (Throwable $exception){

   $error =  "Layer: an error occurred " . $exception->getMessage();
    
}





// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$success = $payment_data['status'];
$invoiceId = $_POST["x_invoice_id"];
$transactionId = $_POST["layer_payment_id"];
$paymentAmount = $_POST["layer_order_amount"];
$paymentFee = '';
$hash = $_POST["hash"];

$transactionStatus = $success ? 'authorized' : 'failed';

/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */
$APISecret = $gatewayParams['APISecret'];
if ($payment_data['status'] == 'failed' || $payment_data['status'] == 'cancelled' ) {
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
}

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

if ($success) {

    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );

}


//## Redirect to invoice details

$location = $systemUrl.'viewinvoice.php?id='.$invoiceId;

header("Location: $location");

if(!empty($error))
echo $error;
else
echo $status;