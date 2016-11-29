<?php

/**
 * @connect_module_class_name CUnitpay
 *
 */
class CUnitpay extends PaymentModule {

    function _initVars(){

        $this->title 		= UNITPAY_TTL;
        $this->description 	= UNITPAY_DSCR;
        $this->sort_order 	= 1;

        $this->Settings = array(
            "CONF_PAYMENTMODULE_UNITPAY_PUBLIC_KEY",
            "CONF_PAYMENTMODULE_UNITPAY_SECRET_KEY",
            "CONF_PAYMENTMODULE_UNITPAY_PAYMENT_STATUS",
            "CONF_PAYMENTMODULE_UNITPAY_ERROR_STATUS",
        );
    }

    function _initSettingFields(){

        $this->SettingsFields['CONF_PAYMENTMODULE_UNITPAY_PUBLIC_KEY'] = array(
            'settings_value' 		=> '',
            'settings_title' 			=> UNITPAY_CFG_PUBLIC_KEY_TITLE,
            'settings_description' 	=> UNITPAY_CFG_PUBLIC_KEY_DESCRIPTION,
            'settings_html_function' 	=> 'setting_TEXT_BOX(0,',
            'sort_order' 			=> 1,
        );

        $this->SettingsFields['CONF_PAYMENTMODULE_UNITPAY_SECRET_KEY'] = array(
            'settings_value' 		=> '',
            'settings_title' 			=> UNITPAY_CFG_SECRET_KEY_TITLE,
            'settings_description' 	=> UNITPAY_CFG_SECRET_KEY_DESCRIPTION,
            'settings_html_function' 	=> 'setting_TEXT_BOX(0,',
            'sort_order' 			=> 1,
        );
        $this->SettingsFields['CONF_PAYMENTMODULE_UNITPAY_PAYMENT_STATUS'] = array(
            'settings_value'                 => '',
            'settings_title'                         => UNITPAY_CFG_PAYMENT_STATUS_TITLE,
            'settings_description'         => UNITPAY_CFG_PAYMENT_STATUS_DESCRIPTION,
            'settings_html_function'         => 'setting_ORDER_STATUS_SELECT(',
            'sort_order'                         => 1,
        );
        $this->SettingsFields['CONF_PAYMENTMODULE_UNITPAY_ERROR_STATUS'] = array(
            'settings_value'                 => '',
            'settings_title'                         => UNITPAY_CFG_ERROR_STATUS_TITLE,
            'settings_description'         => UNITPAY_CFG_ERROR_STATUS_DESCRIPTION,
            'settings_html_function'         => 'setting_ORDER_STATUS_SELECT(',
            'sort_order'                         => 1,
        );
    }

    function after_processing_html( $orderID ){

        $order = ordGetOrder( $orderID );
        $sum = round(100*$order["order_amount"] * $order["currency_value"])/100;
        $account = $orderID;
        $desc = UNITPAY_DESCRIPTION_AFTER_PROCESSING_HTML_1 . $orderID;

        $currency = $order["currency_code"];
        if ($currency == 'RUR'){
            $currency = 'RUB';
        }

        $public_key = $this->_getSettingValue('CONF_PAYMENTMODULE_UNITPAY_PUBLIC_KEY');
        $form = "";

        $form .= "<table width='100%'>\n".
            "	<tr>\n".
            "		<td align='center'>\n";
        $form .= '<form name="unitpay" action="https://unitpay.ru/pay/' . $public_key . '" method="get">';
        $form .= '<input type="hidden" name="sum" value="' . $sum . '" />';
        $form .= '<input type="hidden" name="account" value="' . $account . '" />';
        $form .= '<input type="hidden" name="desc" value="' . $desc . '" />';
        $form .= '<input type="hidden" name="currency" value="' . $currency . '" />';
        $form .= '<input class="button" type="submit" value="' . UNITPAY_TXT_AFTER_PROCESSING_HTML_1 . '">';
        $form .= '</form>';

        $form .= "		</td>\n".
            "	</tr>\n".
            "</table>";

        return $form;
    }

    function after_payment_php( $data ){
        $this->_initVars();
        $method = '';
        $params = array();
        if ((isset($data['params'])) && (isset($data['method'])) && (isset($data['params']['signature']))){
            $params = $data['params'];
            $method = $data['method'];
            $signature = $params['signature'];
            if (empty($signature)){
                $status_sign = false;
            }else{
                $status_sign = $this->verifySignature($params, $method);
            }
        }else{
            $status_sign = false;
        }
//    $status_sign = true;
        if ($status_sign){
            switch ($method) {
                case 'check':
                    $result = $this->check( $params );
                    break;
                case 'pay':
                    $result = $this->pay( $params );
                    break;
                case 'error':
                    $result = $this->error( $params );
                    break;
                default:
                    $result = array('error' =>
                        array('message' => 'Wrong method')
                    );
                    break;
            }
        }else{
            $result = array('error' =>
                array('message' => 'Wrong signature')
            );
        }
        $this->hardReturnJson($result);
    }

    function check( $params )
    {
        // Получаем ID заказа
        $order_id = (int)$params['account'];
        $order = ordGetOrder( $order_id );
        $sum = round(100*$order["order_amount"] * $order["currency_value"])/100;
        $currency = $order["currency_code"];
        if ($currency == 'RUR'){
            $currency = 'RUB';
        }

        if (!isset($params['orderSum']) || ((float)$sum != (float)$params['orderSum'])) {
            $result = array('error' =>
                array('message' => 'The amount of the order not match')
            );
        }elseif (!isset($params['orderCurrency']) || ($currency != $params['orderCurrency'])) {
            $result = array('error' =>
                array('message' => 'The currency of the order not match')
            );
        }
        else{
            $result = array('result' =>
                array('message' => 'Successful request')
            );
        }
        return $result;
    }
    function pay( $params )
    {
        $order_id = (int)$params['account'];
        $order = ordGetOrder( $order_id );
        $sum = round(100*$order["order_amount"] * $order["currency_value"])/100;
        $currency = $order["currency_code"];
        if ($currency == 'RUR'){
            $currency = 'RUB';
        }

        if (!isset($params['orderSum']) || ((float)$sum != (float)$params['orderSum'])) {
            $result = array('error' =>
                array('message' => 'The amount of the order not match')
            );
        }elseif (!isset($params['orderCurrency']) || ($currency != $params['orderCurrency'])) {
            $result = array('error' =>
                array('message' => 'The currency of the order not match')
            );
        }
        else{
            ostSetOrderStatusToOrder($order_id, $this->_getSettingValue('CONF_PAYMENTMODULE_UNITPAY_PAYMENT_STATUS'));
            $result = array('result' =>
                array('message' => 'Successful request')
            );
        }
        return $result;
    }

    function error( $params )
    {
        $order_id = (int)$params['account'];
        ostSetOrderStatusToOrder($order_id, $this->_getSettingValue('CONF_PAYMENTMODULE_UNITPAY_ERROR_STATUS'));
        $result = array('result' =>
            array('message' => 'Successful request')
        );
        return $result;
    }

    function getSignature($method, array $params, $secretKey)
    {
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        array_push($params, $secretKey);
        array_unshift($params, $method);
        return hash('sha256', join('{up}', $params));
    }

    function verifySignature($params, $method)
    {
        $secret = $this->_getSettingValue('CONF_PAYMENTMODULE_UNITPAY_SECRET_KEY');
        return $params['signature'] == $this->getSignature($method, $params, $secret);
    }

    function hardReturnJson( $arr )
    {
        header('Content-Type: application/json');
        $result = json_encode($arr);
        die($result);
    }

}