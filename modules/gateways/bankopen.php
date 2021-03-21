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

require_once __DIR__.'/bankopen/layer_api.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


function bankopen_MetaData()
{
    return array(
        'DisplayName' => 'Bankopen',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}


function bankopen_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Bankopen',
        ),

        // the yesno field type displays a single checkbox option
        'testMode' => array(
            'FriendlyName' => 'Enable sandbox',
            'Type' => 'yesno',
            'Description' => 'Tick to enable sandbox mode',
        ),

        'SandboxAPIKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter sandbox API Key',
        ),
        'SandboxAPISecret' => array(
            'FriendlyName' => 'API Secret',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter Sandbox API Secret',
        ),
        'LiveAPIKey' => array(
            'FriendlyName' => 'Live API Key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter live API Key',
        ),
        'LiveAPISecret' => array(
            'FriendlyName' => 'Live API Secret',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter live API Secret',
        ),
    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */




function bankopen_link($params)
{
    // Gateway Configuration Parameters


    $testMode = $params['testMode'];

    if($testMode)
    {
    $APIKey = $params['SandboxAPIKey'];
    $APISecret = $params['SandboxAPISecret'];
    $remote_script = "https://sandbox-payments.open.money/layer";
    $environment = 'test';

    }else{

    $APIKey = $params['LiveAPIKey'];
    $APISecret = $params['LiveAPISecret'];
    $remote_script = "https://payments.open.money/layer";
    $environment = 'production';

    }

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    $postfields = array();
    $postfields['username'] = $username;
    $postfields['invoice_id'] = $invoiceId;
    $postfields['description'] = $description;
    $postfields['amount'] = $amount;
    $postfields['currency'] = $currencyCode;
    $postfields['first_name'] = $firstname;
    $postfields['last_name'] = $lastname;
    $postfields['email'] = $email;
    $postfields['address1'] = $address1;
    $postfields['address2'] = $address2;
    $postfields['city'] = $city;
    $postfields['state'] = $state;
    $postfields['postcode'] = $postcode;
    $postfields['country'] = $country;
    $postfields['phone'] = $phone;
    $postfields['callback_url'] = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';
    $postfields['return_url'] = $returnUrl;

    $LayeredJs = $systemUrl . 'modules/gateways/' . $moduleName.'/layer_checkout.js';
    $CallBackUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName.'.php';

    $sample_data = [
        'amount' => $amount,
        'currency' => $currencyCode,
        'name'  => $firstname,
        'email_id' => $email,
        'contact_number' => $phone,
        'mtx' => ''
    ];
    //main logic
$error = '';
$tranid=date("ymd").'-'.rand(1,100);

$sample_data['mtx']=$tranid; //unique transaction id to be passed for each transaction 
$layer_api = new LayerApi($environment,$APIKey,$APISecret);
$layer_payment_token_data = $layer_api->create_payment_token($sample_data);
   
if(empty($error) && isset($layer_payment_token_data['error'])){
	$error = 'E55 Payment error. ' . ucfirst($layer_payment_token_data['error']);  
	if(isset($layer_payment_token_data['error_data']))
	{
		foreach($layer_payment_token_data['error_data'] as $d)
			$error .= " ".ucfirst($d[0]);
	}
}

if(empty($error) && (!isset($layer_payment_token_data["id"]) || empty($layer_payment_token_data["id"]))){				
    $error = 'Payment error. ' . 'Layer token ID cannot be empty.';        
}   

if(!empty($layer_payment_token_data["id"]))
    $payment_token_data = $layer_api->get_payment_token($layer_payment_token_data["id"]);
    
if(empty($error) && !empty($payment_token_data)){
    if(isset($layer_payment_token_data['error'])){
        $error = 'E56 Payment error. ' . $payment_token_data['error'];            
    }

    if(empty($error) && $payment_token_data['status'] == "paid"){
        $error = "Layer: this order has already been paid.";            
    }

    if(empty($error) && $payment_token_data['amount'] != $sample_data['amount']){
        $error = "Layer: an amount mismatch occurred.";
    }

    $jsdata['payment_token_id'] = html_entity_decode((string) $payment_token_data['id'],ENT_QUOTES,'UTF-8');
    $jsdata['accesskey']  = html_entity_decode((string) $APIKey,ENT_QUOTES,'UTF-8');
        
	$hash = create_hash(array(
        'layer_pay_token_id'    => $payment_token_data['id'],
        'layer_order_amount'    => $payment_token_data['amount'],
        'tranid'    => $tranid,
    ),$APIKey,$APISecret);

    $html = '<script src="'.$remote_script.'"></script>';

    $responseUrl = 
        
    $html .=  '<form action="'.$CallBackUrl.'" method="post" style="display: none" name="layer_payment_int_form">
		<input type="hidden" name="layer_pay_token_id" value="'.$payment_token_data['id'].'">
        <input type="hidden" name="tranid" value="'.$tranid.'">
        <input type="hidden" name="layer_order_amount" value="'.$payment_token_data['amount'].'">
        <input type="hidden" id="layer_payment_id" name="layer_payment_id" value="">
        <input type="hidden" id="fallback_url" name="fallback_url" value="">
        <input type="hidden" name="hash" value="'.$hash.'">
        </form>';
    $html .= "<script>";
    $html .= "var layer_params = " . json_encode( $jsdata ) . ';'; 
    $html .="</script>";

    $html .= '<script type="text/javascript" src="'.$LayeredJs.'"></script>';
}


    if(!empty($error))
    {
        $htmlOutput .= $error;
    }

    if (isset($html)) {

    $htmlOutput .= '<button id="submit" name="submit" type="button" class="btn btn-info" onclick="triggerLayer();">Pay Now</button>';

    $htmlOutput .= $html;
    
    }

    return $htmlOutput;
}


function create_hash($data,$APIKey,$APISecret){
    ksort($data);
    $hash_string = $APIKey;
    foreach ($data as $key=>$value){
        $hash_string .= '|'.$value;
    }
    return hash_hmac("sha256",$hash_string,$APISecret);
}

function verify_hash($data,$rec_hash,$APIKey,$APISecret){
    $gen_hash = create_hash($data,$APIKey,$APISecret);
    if($gen_hash === $rec_hash){
        return true;
    }
    return false;
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 * @return array Transaction response status
 */
function bankopen_refund($params)
{
    // Gateway Configuration Parameters
    $APIKey = $params['APIKey'];
    $APISecret = $params['APISecret'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to initiate refund and interpret result

    return array(
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
        // Unique Transaction ID for the refund transaction
        'transid' => $refundTransactionId,
        // Optional fee amount for the fee value refunded
        'fees' => $feeAmount,
    );
}

/**
 * Cancel subscription.
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/subscription-management/
 *
 * @return array Transaction response status
 */
function bankopen_cancelSubscription($params)
{
    // Gateway Configuration Parameters
    $APIKey = $params['APIKey'];
    $APISecret = $params['APISecret'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Subscription Parameters
    $subscriptionIdToCancel = $params['subscriptionID'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // perform API call to cancel subscription and interpret result

    return array(
        // 'success' if successful, any other value for failure
        'status' => 'success',
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $responseData,
    );
}
