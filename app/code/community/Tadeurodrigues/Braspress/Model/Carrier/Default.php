<?php
 
/**
* Our test shipping method module adapter
*/
class TadeuRodrigues_Braspress_Model_Carrier_Default extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
  /**
   * unique internal shipping method identifier
   *
   * @var string [a-z0-9_]
   */
  protected $_code          =   'braspress';
  protected $_url_base_soap =   'http://tracking.braspress.com.br/wscalculafreteisapi.dll/wsdl/IWSCalcFrete?wsdl';
  protected $_url_base_web	=	'http://www.braspress.com.br/cotacaoXml?param=';
  
  protected $_result        =   null;
  protected $params         =   array();
 
  /**
   * Collect rates for this shipping method based on information in $request
   *
   * @param Mage_Shipping_Model_Rate_Request $data
   * @return Mage_Shipping_Model_Rate_Result
   */
  public function collectRates(Mage_Shipping_Model_Rate_Request $request)
  {
    // skip if not enabled
	if(!$this->isActive()){
		return false;
	}

    if($this->getConfigData('weight_type') == 2){
        $Weight = $request->getPackageWeight()/10000;
    }else{
        $Weight = $request->getPackageWeight();
    }
	
	$quote	=	$this->getQuote();
	$cart	=	$this->getCart();
	
	// cnpj default
	
	switch($this->getConfigData('use_default')){
		case 0:
			$cnpjdes = $quote->getData("customer_taxvat");
			break;
		case 1:
			$cnpjdes = $this->getConfigData('cnpj');
			break;
		case 2:
			$cnpjdes = $this->getConfigData('cnpj_default');
			break;
			
		default:
			$cnpjdes = $quote->getData("customer_taxvat");
		
	}

	$this->params = array(
    "cnpj"          =>   preg_replace( '/[^0-9]/', '', $this->getConfigData('cnpj') ),
    "emporigem"		=>	 2,
    "ceporigem"     =>   preg_replace( '/[^0-9]/', '', Mage::getStoreConfig('shipping/origin/postcode', $this->getStore())),
    "cepdestino"    =>   preg_replace( '/[^0-9]/', '', $request->getDestPostcode() ),
    "cnpjrem"       =>   preg_replace( '/[^0-9]/', '', $this->getConfigData('cnpj') ),
    "cnpjdes"       =>   preg_replace( '/[^0-9]/', '', $cnpjdes),
    "tipofrete"     =>   $this->getConfigData('shipping_type'),
    "peso"          =>   $Weight,
    "valornf"       =>   number_format($request->getPackageValue(), 2, '.', ''),
    "volume"        =>   $cart->getItemsCount(),
    "modal"         =>   $this->getConfigData('modal_type')
	);
	
	
	if($this->getConfigData('access_type') == 1){
	    $calFrete = $this->CalculaFrete();
		
	}else{
		$params = implode(',', $this->params);
		
		$calFrete = simplexml_load_file($this->_url_base_web.$params);
	}                                     

    $this->_result = Mage::getModel('shipping/rate_result');
	
	if($this->getConfigData('debug')){
		//Mage::log($request->getData(), null, 'request.log');
		//Mage::log($quote->getData(), null, 'quote.log');
		Mage::log("--------ENVIADO--------------------", null, 'braspress.log');
		Mage::log($this->params, null, 'braspress.log');
		Mage::log("-----------------------------------", null, 'braspress.log');
		Mage::log("--------RESPOSTA-------------------", null, 'braspress.log');
		Mage::log($calFrete, null, 'braspress.log');
		Mage::log("-----------------------------------", null, 'braspress.log');
		
		
    //Mage::log($quote->getData(),null,'teste1.log');        // order data
    //Mage::log(Mage::helper('checkout')->getQuote()->getShippingAddress()->getData(),null,'teste2.log'); // shipping data
	//Mage::log(Mage::helper('tax'), null, 'tax.log');	
	}
    
    if($calFrete->MSGERRO != "OK"){
        $this->_throwError('specificerrmsg', $calFrete->MSGERRO, __LINE__);  
    }else{
        if($calFrete->TOTALFRETE > (float) 0 ){
            $method = Mage::getModel('shipping/rate_result_method');
            
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
         
            // record method information
            $method->setMethod($this->_code);

			Mage::log($calFrete->PRAZOENTREGA, null, 'braspress_test.log');
			
			if($this->getConfigData('delivery_time')){
				$prazo = $calFrete->PRAZOENTREGA;
				switch($prazo){
					case 0:
						$prazomsg = ' entrega imedita';
					break;
					case 1:
						$prazomsg = ' prazo de 1 dia';
					break;
					default:
						$prazomsg = ' prazo de '.$prazo.' dias';
					break;
				}
				
				$method_name = $this->getConfigData('method_name').$prazomsg;
			}else{
				$method_name = $this->getConfigData('method_name');
			}
		
            $method->setMethodTitle($method_name);
            
            $method->setPrice($calFrete->TOTALFRETE);
         
            // add this rate to the result
            $this->_result->append($method);
        }
    }
    
    return $this->_result;
    //return Zend_Debug::Dump($calFrete->SUBTOTAL);

  }

	public function connectionSoap(){
		$client = new SoapClient($this->_url_base_soap,
	    array(
	      "trace"    			=> 1,         // enable trace to view what is happening
	      "exceptions"			=> 0,            // disable exceptions
	      "cache_wsdl"			=> 0)             // disable any caching on the wsdl, encase you alter the wsdl server
	    );
		
		return $client;
	}

	public function CalculaFrete(){
		$client = $this->connectionSoap();

	    $calFrete       =   $client->CalculaFrete(  $this->params['cnpj'], 
	                                                $this->params['emporigem'], 
	                                                $this->params['ceporigem'], 
	                                                $this->params['cepdestino'], 
	                                                $this->params['cnpjrem'], 
	                                                $this->params['cnpjdes'], 
	                                                $this->params['tipofrete'], 
	                                                $this->params['peso'], 
	                                                $this->params['valornf'], 
	                                                $this->params['volume']
	                                             ); 
		return $calFrete;
	}
	
	public function ConsultaPrazoEntrega(){
		$client = $this->connectionSoap();
		
		$ConsultaPrazoEntrega = $client->ConsultaPrazoEntrega(
											$this->params['ceporigem'],
											$this->params['cepdestino'],
											$this->params['cnpj']
										);
		
		return $ConsultaPrazoEntrega;
	}

	public function getSession(){
		return Mage::getSingleton('checkout/session');
	}

	public function getQuote(){
		return $this->getSession()->getQuote();
	}
	
	public function getCart(){
		return Mage::getSingleton('checkout/cart');
	}

     /**
     * This method is used when viewing / listing Shipping Methods with Codes programmatically
     */
    public function getAllowedMethods() {
        return array($this->_code => $this->getConfigData('title'));
    }
  
    protected function _throwError($message, $log = null, $line = 'NO LINE', $custom = null){
    
        $this->_result = null;
        $this->_result = Mage::getModel('shipping/rate_result');
        
        // Get error model
        $error = Mage::getModel('shipping/rate_result_error');
        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->getConfigData('title'));
        
        if(is_null($custom) || $this->getConfigData($message) == ''){
        //Log error
        Mage::log($this->_code . ' [' . $line . ']: ' . $log);
            $error->setErrorMessage($this->getConfigData($message));
        }else{
            //Log error
        Mage::log($this->_code . ' [' . $line . ']: ' . $log);
            $error->setErrorMessage(sprintf($this->getConfigData($message), $custom));
        }        
        
        // Apend error
            $this->_result->append($error);
    }
}