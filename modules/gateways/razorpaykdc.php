<?php
/**
 * ## WHMCS Razorpay Payment Gateway Module
 * 
 * ## Changelog
 * =========
 * 
 * # 1.1 (2020-08-09)
 * - Enaabled Refund.
 * - Renamed the module, to avoid conflict, if any. 
 * 
 * # 1.0 (2015-02-15)
 * - Inital Release.
 * 
 */
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 * @return array
 */
function razorpaykdc_MetaData()
{
    return array(
        'DisplayName' => 'Razorpay by KDC',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 * @return array
 */
function razorpaykdc_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Razorpay',
        ),
        'keyId' => array(
            'FriendlyName' => 'Key Id',
            'Type' => 'text',
            'Size' => '50',
            //'Default' => 'rzp_',
            'Description' => 'Razorpay "Key Id". Available <a href="https://dashboard.razorpay.com/#/app/keys" target="_blank" style="bottom-border:1px dotted;">HERE</a>',
        ),
        'keySecret' => array(
            'FriendlyName' => 'Key Secret',
            'Type' => 'text',
            'Size' => '50',
            //'Default' => '',
            'Description' => 'Razorpay "Key Secret" shared during activation API Key',
        ),
        'refundSpeed' => array(
            'FriendlyName' => 'Refund Speed',
            'Type' => 'dropdown',
            'Options' => array(
                'normal' => 'Normal Refund',
                'optimum' => 'Instant Refund',
            ),
            'Size' => '50',
            'Default' => 'optimum',
            'Description' => 'You can configure the speed at which all the refunds should be processed for your customers. <a href="https://dashboard.razorpay.com/app/config#default-refund-container" target="_blank" style="bottom-border:1px dotted;">Manage default</a>.',
        ),
        'themeLogo' => array(
            'FriendlyName' => 'Logo URL',
            'Type' => 'text',
            'Size' => '50',
            //'Default' => 'http://',
            'Description' => 'ONLY "http<strong>s</strong>://"; else leave blank.<br/><small>Size: 128px X 128px (or higher) | File Type: png/jpg/gif/ico</small>',
        ),
        'themeColor' => array(
            'FriendlyName' => 'Theme Color',
            'Type' => 'text',
            'Size' => '15',
            'Default' => '#15A4D3',
            'Description' => 'The colour of checkout form elements',
        ),
    );
}

/**
 * Payment link.
 * Required by third party payment gateway modules only.
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 * @param array $params Payment Gateway Module Parameters
 * @return string
 */
function razorpaykdc_link($params)
{
    // Gateway Configuration Parameters
    $keyId = $params['keyId'];
    $themeLogo = $params['themeLogo'];
    $themeColor = $params['themeColor'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount']*100; // Required to be converted to Paisa.
    $currencyCode = $params['currency'];

    // Client Parameters
    $clientName = $params['clientdetails']['firstname'].' '.$params['clientdetails']['lastname'];
    $clientEmail = $params['clientdetails']['email'];
    $clientPhone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $whmcsVersion = $params['whmcsVersion'];
	$callbackUrl = $params['systemurl'] . '/modules/gateways/callback/razorpay.php';
	$checkoutUrl = 'https://checkout.razorpay.com/v1/checkout.js';
	
    $html = '<form name="razorpay-form" id="razorpay-form" action="'.$callbackUrl.'" method="POST" onSubmit="if(!razorpay_open) razorpaySubmit(); return razorpay_submit;">
                <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" />
                <input type="hidden" name="merchant_order_id" id="merchant_order_id" value="'.$invoiceId.'"/>
                <input type="button" value="Click Here to Pay" onClick="razorpaySubmit()"/>
            </form>';
    
    $js = '<script src="'.$checkoutUrl.'"></script>';

    $js .= "<script>
            var razorpay_open = false;
            var razorpay_submit = false;
            var razorpay_options = {
                'key': '".$keyId."',
                'amount': '".$amount."',
                'currency': '".$currencyCode."',
                'name': '".$companyName."',
                'description': 'Inv#".$invoiceId."',";
	
	if(isset($themeLogo)&&$themeLogo!=""){
		if(strpos($themeLogo,'https://')!== false){
			$js .= "
                'image': '".$themeLogo."',";
		}
	}
	if(isset($themeColor)&&$themeColor!=""){
		$js .= "
                'theme': {
                    'color': '".$themeColor."'
                },";
	}
    
	$js .= "
                'handler': function (transaction) {
                    razorpay_submit = true;
                    document.getElementById('razorpay_payment_id').value = transaction.razorpay_payment_id;
                    document.getElementById('razorpay-form').submit();
                },
                'prefill': {
                    'name': '".$clientName."',
                    'email': '".$clientEmail."',
                    'contact': '".$clientPhone."'
                },
                notes: {
                    'whmcs_invoice_id': '".$invoiceId."',
                    'whmcs_version': '".$whmcsVersion."'
                },
                netbanking: true
            };
            
            function razorpaySubmit(){                  
                var rzp1 = new Razorpay(razorpay_options);
                rzp1.open();
                razorpay_open = true;
                rzp1.modal.options.backdropClose = false;
            }    

            </script>";

    return $html.$js;
}

/**
 * Refund transaction.
 * Called when a refund is requested for a previously successful transaction.
 * @param array $params Payment Gateway Module Parameters
 * @return array Transaction response status
 */
function razorpaykdc_refund($params)
{
    // Gateway Configuration Parameters
    $keyId = $params['keyId'];
    $keySecret = $params['keySecret'];
    $refundSpeed = $params['refundSpeed'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount']*100; // Required to be converted to Paisa.
    $currencyCode = $params['currency'];

    $success = true;
    $error = "";
    
    try {
        $url = 'https://api.razorpay.com/v1/payments/'.$transactionIdToRefund.'/refund';
        $fields_string = 'amount='.$refundAmount.'&speed='.$refundSpeed;
    
        //cURL Request
        $ch = curl_init();
    
        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_USERPWD, $keyId . ":" . $keySecret);
        curl_setopt($ch,CURLOPT_TIMEOUT, 60);
        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE);
    
        //execute post
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    
        if($result === false) {
            $success = false;
            $error = 'Curl error: ' . curl_error($ch);
        }
        else {
            $response_array = json_decode($result, true);
            //Check success response
            if($http_status === 200 and isset($response_array['error']) === false){
                $success = true;    
            }
            else {
                $success = false;
    
                if(!empty($response_array['error']['code'])) {
                    $error = $response_array['error']['code'].":".$response_array['error']['description'];
                }
                else {
                    $error = "RAZORPAY_ERROR:Invalid Response <br/>".$result;
                }
            }
        }
            
        //close connection
        curl_close($ch);
    }
    catch (Exception $e) {
        $success = false;
        $error ="WHMCS_ERROR:Request to Razorpay Failed";
    }
    
    if ($success === true) {
        # Successful
        return array(
            'status' => 'success',
            'rawdata' => $response_array,
            'transid' => $response_array['id'],
            'fees' => 0,
        );
    } 
    
}