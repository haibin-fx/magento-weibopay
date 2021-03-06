<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    CosmoCommerce
 * @package     CosmoCommerce_Sinapay
 * @copyright   Copyright (c) 2009-2013 CosmoCommerce,LLC. (http://www.cosmocommerce.com)
 * @contact :
 * T: +86-021-66346672
 * L: Shanghai,China
 * M:sales@cosmocommerce.com
 */
class CosmoCommerce_Sinapay_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Order instance
     */
    protected $_order; 

    /**
     *  Get order
     *
     *  @param    none
     *  @return	  Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if ($this->_order == null)
        {
            $session = Mage::getSingleton('checkout/session');
            $this->_order = Mage::getModel('sales/order');
            $this->_order->loadByIncrementId($session->getLastRealOrderId());
        }
        return $this->_order;
    }

    /**
     * When a customer chooses Sinapay on Checkout/Payment page
     *
     */
     
    public function payAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setSinapayPaymentQuoteId($session->getQuoteId());

        $order = $this->getOrder();

        if (!$order->getId())
        {
            $this->norouteAction();
            return;
        }

        $order->addStatusToHistory(
        $order->getStatus(),
        Mage::helper('sinapay')->__('Customer was redirected to payment center')
        );
        $order->save();

        
        $this->loadLayout();
        $this->renderLayout();
    }

    
    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setSinapayPaymentQuoteId($session->getQuoteId());

        $order = $this->getOrder();

        if (!$order->getId())
        {
            $this->norouteAction();
            return;
        }

        $order->addStatusToHistory(
        $order->getStatus(),
        Mage::helper('sinapay')->__('Customer was redirected to Sinapay')
        );
        $order->save();

        
        $this->getResponse()
        ->setBody($this->getLayout()
        ->createBlock('sinapay/redirect')
        ->setOrder($order)
        ->toHtml());

        $session->unsQuoteId();
    }

    public function notifyAction()
    {
        if ($this->getRequest()->isPost())
        {
            $postData = $this->getRequest()->getPost();
            $method = 'post';


        } else if ($this->getRequest()->isGet())
        {
            $postData = $this->getRequest()->getQuery();
            $method = 'get';

        } else
        {
            return;
        }
		$sinapay = Mage::getModel('sinapay/payment');
		
		$partner=$sinapay->getConfigData('partner_id');
		$security_code=$sinapay->getConfigData('security_code');
		$sendemail=$sinapay->getConfigData('sendemail'); 
		
        
        $pay_params=array();
        $pay_params["merchantAcctId"]=$postData["merchantAcctId"];
        $pay_params["version"]=$postData["version"];
        $pay_params["language"]=$postData["language"];
        $pay_params["signType"]=$postData["signType"];
        $pay_params["payType"]=$postData["payType"];
        $pay_params["bankId"]=$postData["bankId"];
        $pay_params["orderId"]=$postData["orderId"];
        $pay_params["orderTime"]=$postData["orderTime"];
        $pay_params["orderAmount"]=$postData["orderAmount"];
        $pay_params["dealId"]=$postData["dealId"];
        $pay_params["bankDealId"]=$postData["bankDealId"];
        $pay_params["dealTime"]=$postData["dealTime"];
        $pay_params["payAmount"]=$postData["payAmount"];
        $pay_params["fee"]=$postData["fee"];
        $pay_params["ext1"]=$postData["ext1"];
        $pay_params["ext2"]=$postData["ext2"];
        $pay_params["payResult"]=$postData["payResult"];
        $pay_params["payIp"]=$postData["payIp"];
        $pay_params["errCode"]=$postData["errCode"];
        $pay_params["signMsg"]=$postData["signMsg"];
        
    //?payAmount=2&dealTime=20140112120149&signType=1&merchantAcctId=200100100120000414386201101&orderTime=20140112040001&dealId=2014011215666641&version=v2.3&bankId=CMB&fee=1&bankDealId=03140112022052399&payResult=10&orderAmount=2&signMsg=383febc4c6bfdc584de5293ca81ba7d8&language=1&payIp=113.139.236.208&orderId=100000011
    //?payAmount=2&dealTime=20140110224635&signType=1&merchantAcctId=200100100120000414386201101&orderTime=20140110024531&dealId=2014011015604200&version=v2.3&fee=1&payResult=10&orderAmount=2&signMsg=dddfb9b3716e40092e7324b2ee198286&language=1&payIp=1.83.163.26&orderId=100000010
        
        $orderId=$postData["orderId"];
		$params_str = "";
		$signMsg = "";
		foreach($pay_params as $key=>$val){
			if($key!="signMsg" && !is_null($val) && @$val!="")
			{
				$params_str .= $key."=".$val."&";
			}
		}
        $params_str .= "key=" . $security_code;
        $signMsg = strtolower(md5($params_str));
        
        
        
        Mage::log($pay_params,null,'weibopay_callback.log');
        Mage::log($signMsg,null,'weibopay_callback.log');
		
		if ( $signMsg == $postData["signMsg"])  {
			if($postData['payResult'] == '10' ) {   
		
                Mage::log('交易成功',null,'weibopay_callback.log');
				$order = Mage::getModel('sales/order');
				$order->loadByIncrementId($orderId);
                if ($order->getState() == 'new' || $order->getState() == 'processing' || $order->getState() == 'pending_payment' || $order->getState() == 'payment_review') {
                    //$order->setSinapayTradeno($postData['trade_no']);
                    $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                    if($sendemail){
                        $order->sendOrderUpdateEmail(true,'买家已付款,交易成功结束。');
                    }
                    $order->addStatusToHistory(
                    $sinapay->getConfigData('order_status_payment_accepted'),
                    Mage::helper('sinapay')->__('买家已付款,交易成功结束。'));
                    try{
                        $order->save();
                        echo "<result>1</result><redirecturl><![CDATA[".Mage::getUrl('checkout/onepage/success')."]]></redirecturl>";
                    
                        Mage::log('交易完成',null,'weibopay_callback.log');
						exit();
                    } catch(Exception $e){
                        
                    }
                }
			}
			else {
                Mage::log('交易失败',null,'weibopay_callback.log');
				exit();
			}	

		} else {
            Mage::log('交易失败',null,'weibopay_callback.log');
			exit();
		}
    }

	public function get_verify($url,$time_out = "60") {
		$urlarr     = parse_url($url);
		$errno      = "";
		$errstr     = "";
		$transports = "";
		if($urlarr["scheme"] == "https") {
			$transports = "ssl://";
			$urlarr["port"] = "443";
		} else {
			$transports = "tcp://";
			$urlarr["port"] = "80";
		}
		$fp=@fsockopen($transports . $urlarr['host'],$urlarr['port'],$errno,$errstr,$time_out);
		if(!$fp) {
			die("ERROR: $errno - $errstr<br />\n");
		} else {
			fputs($fp, "POST ".$urlarr["path"]." HTTP/1.1\r\n");
			fputs($fp, "Host: ".$urlarr["host"]."\r\n");
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
			fputs($fp, "Content-length: ".strlen($urlarr["query"])."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			fputs($fp, $urlarr["query"] . "\r\n\r\n");
			while(!feof($fp)) {
				$info[]=@fgets($fp, 1024);
			}
			fclose($fp);
			$info = implode(",",$info);
			$arg="";
			while (list ($key, $val) = each ($_POST)) {
				$arg.=$key."=".$val."&";
			}

		return $info;
		}

	}
    /**
     *  Sinapay response router
     *
     *  @param    none
     *  @return	  void
     public function notifyAction()
     {
     $model = Mage::getModel('sinapay/payment');
     
     if ($this->getRequest()->isPost()) {
     $postData = $this->getRequest()->getPost();
     $method = 'post';
     } else if ($this->getRequest()->isGet()) {
     $postData = $this->getRequest()->getQuery();
     $method = 'get';
     } else {
     $model->generateErrorResponse();
     }
     $order = Mage::getModel('sales/order')
     ->loadByIncrementId($postData['reference']);
     if (!$order->getId()) {
     $model->generateErrorResponse();
     }
     if ($returnedMAC == $correctMAC) {
     if (1) {
     $order->addStatusToHistory(
     $model->getConfigData('order_status_payment_accepted'),
     Mage::helper('sinapay')->__('Payment accepted by Sinapay')
     );
     
     $order->sendNewOrderEmail();
     if ($this->saveInvoice($order)) {
     //                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
     }
     
     } else {
     $order->addStatusToHistory(
     $model->getConfigData('order_status_payment_refused'),
     Mage::helper('sinapay')->__('Payment refused by Sinapay')
     );
     
     // TODO: customer notification on payment failure
     }
     
     $order->save();
     } else {
     $order->addStatusToHistory(
     Mage_Sales_Model_Order::STATE_CANCELED,//$order->getStatus(),
     Mage::helper('sinapay')->__('Returned MAC is invalid. Order cancelled.')
     );
     $order->cancel();
     $order->save();
     $model->generateErrorResponse();
     }
     }
     */
     /**
     *  Save invoice for order
     *
     *  @param    Mage_Sales_Model_Order $order
     *  @return	  boolean Can save invoice or not
     */
    protected function saveInvoice(Mage_Sales_Model_Order $order)
    {
        if ($order->canInvoice())
        {
            $convertor = Mage::getModel('sales/convert_order');
            $invoice = $convertor->toInvoice($order);
            foreach ($order->getAllItems() as $orderItem)
            {
                if (!$orderItem->getQtyToInvoice())
                {
                    continue ;
                }
                $item = $convertor->itemToInvoiceItem($orderItem);
                $item->setQty($orderItem->getQtyToInvoice());
                $invoice->addItem($item);
            }
            $invoice->collectTotals();
            $invoice->register()->capture();
            Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();
            return true;
        }

        return false;
    }

    /**
     *  Success payment page
     *
     *  @param    none
     *  @return	  void
     */
    public function successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getSinapayPaymentQuoteId());
        $session->unsSinapayPaymentQuoteId();

        $order = $this->getOrder();

        if (!$order->getId())
        {
            $this->norouteAction();
            return;
        }

        $order->addStatusToHistory(
        $order->getStatus(),
        Mage::helper('sinapay')->__('Customer successfully returned from Sinapay')
        );

        $order->save();

        $this->_redirect('checkout/onepage/success');
    }

    /**
     *  Failure payment page
     *
     *  @param    none
     *  @return	  void
     */
    public function errorAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $errorMsg = Mage::helper('sinapay')->__(' There was an error occurred during paying process.');

        $order = $this->getOrder();

        if (!$order->getId())
        {
            $this->norouteAction();
            return;
        }
        if ($order instanceof Mage_Sales_Model_Order && $order->getId())
        {
            $order->addStatusToHistory(
            Mage_Sales_Model_Order::STATE_CANCELED,//$order->getStatus(),
            Mage::helper('sinapay')->__('Customer returned from Sinapay.').$errorMsg
            );

            $order->save();
        }

        $this->loadLayout();
        $this->renderLayout();
        Mage::getSingleton('checkout/session')->unsLastRealOrderId();
    }
	
	
    
	public function sign($prestr) {
		$mysign = md5($prestr);
		return $mysign;
	}
    
	public function para_filter($parameter) {
		$para = array();
		while (list ($key, $val) = each ($parameter)) {
			if($key == "sign" || $key == "sign_type" || $val == "")continue;
			else	$para[$key] = $parameter[$key];

		}
		return $para;
	}
	
	public function arg_sort($array) {
		ksort($array);
		reset($array);
		return $array;
	}

	public function charset_encode($input,$_output_charset ,$_input_charset ="GBK" ) {
		
		$output = "";
		if($_input_charset == $_output_charset || $input ==null) {
			$output = $input;
		} elseif (function_exists("mb_convert_encoding")){
			$output = mb_convert_encoding($input,$_output_charset,$_input_charset);
		} elseif(function_exists("iconv")) {
			$output = iconv($_input_charset,$_output_charset,$input);
		} else die("sorry, you have no libs support for charset change.");
		
		return $output;
	}	
}
