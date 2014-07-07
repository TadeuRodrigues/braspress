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
  protected $_url_base_web	=	'http://tracking.braspress.com.br/trk/trkisapi.dll/PgCalcFrete_XML?param=';
  
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

	$quote	=	$this->getQuote();
	$cart	=	$this->getCart();

	$method = Mage::getModel('shipping/rate_result_method');
	$method_name = '';

	/*  Calcula o PESO CUBADO, que é a multiplicação das medidas de cada volume com sua quantidade e a densidade estabelecida como padrão por todas as transportadoras brasileiras.
		Se o PESO CUBADO for maior que o PESO REAL, o peso cubado é enviado para o web service calcular o frete. Caso contrário, o peso real é enviado.
		Esta é a diferença entre, por exemplo, carregar 10 kg de chumbo e 10 kg de pena. O espaço necessário para carregar o primeiro volume é muito menor que o requerido pelo segundo.  */
	//  Verifica se os campos "comprimento", "largura" e "altura" existem  //
	$atributo_comprimento_existe = ($attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', 'length')) && ($attribute->getId());
	$atributo_largura_existe = ($attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', 'width')) && ($attribute->getId());
	$atributo_altura_existe = ($attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', 'height')) && ($attribute->getId());

	$peso_cubado_habilita = $atributo_comprimento_existe && $atributo_largura_existe && $atributo_altura_existe;
	//  =================================================================  //

	if ($peso_cubado_habilita) {
		//  Verifica a unidade de medida a ser utilizada  //
		switch ($this->getConfigData('measure_type')) {
			case 'mm': $unidade_medida = 0.001; break;
			case 'cm': $unidade_medida = 0.01; break;
			case 'dm': $unidade_medida = 0.1; break;
			case 'm':
			default: $unidade_medida = 1; break;
		}
		//  ============================================  //

		//  Verifica a densidade a ser utilizada, de acordo com o modal. Rodoviário = 300 Aéreo = 167  //
		$densidade = $this->getConfigData('modal_type') == 'R' ? 300 : 167;

		$peso_cubado = $peso_real = $peso_total = 0.00;

		//  Calcula o peso cubado de cada item da sacola  //
		foreach (Mage::getSingleton('checkout/session')->getQuote()->getAllItems() as $item) {
			//  Carrega os dados do produto  //
			$product = Mage::getSingleton('catalog/product')->load($item->getProduct()->getId());

			//  Calcula a multiplicação do comprimento com a largura e com a altura, depois arredonda com duas casas decimais, pois uma precisão maior afeta negativamente o valor do frete  //
			$peso_cubado_volume = (float)number_format((float)number_format($product->getData('length') * $unidade_medida, 3, '.', '') *
													   (float)number_format($product->getData('width') * $unidade_medida, 3, '.', '') *
													   (float)number_format($product->getData('height') * $unidade_medida, 3, '.', ''), 2, '.', '');
			//  ===========================================================================================================================================================================  //

			if ($peso_cubado_habilita && $peso_cubado_volume > 0.00) {
				//  Multiplica o valor acima com a densidade, obtendo o peso cubado do volume  //
				$peso_cubado_volume = (float)number_format($peso_cubado_volume * $densidade, 3, '.', '');

				//  Multiplica o peso cubado do volume com a quantidade, obtendo o peso cubado total do item  //
				$peso_cubado += (float)number_format($peso_cubado_volume * $item->getQty(), 3, '.', '');
			} else {
				//  Desabilita o peso cubado  //
				$peso_cubado = 0.00;
				$peso_cubado_habilita = false;
				//  ========================  //
			}

			//  Multiplica o peso real do produto com a quantidade, obtendo o peso real do item  //
			$peso_real += (float)number_format($item->getWeight() * $item->getQty(), 3, '.', '');
		}
		//  ============================================  //

		//  Se o peso cubado é maior que o real, pega o cubado para enviar ao web service, caso contrário, pega o real  //
		$Weight = $peso_cubado_habilita && $peso_cubado > $peso_real ? $peso_cubado : $peso_real;
	} else {
		//  Pega o peso total dos itens  //
		$Weight = $request->getPackageWeight();
	}
	//  ================================================================================================================================================================================  //

	//  Se a configuração do peso for em gramas, transforma o peso total para quilos  //
    if ($this->getConfigData('weight_type') == 2) $Weight /= 1000;

	//  Verifica o CNPJ do destinatário  //
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
			break;
	}
	//  ===============================  //

	//  Constroi os parâmetros da chamada ao web service  //
	$this->params = array("cnpj"       => preg_replace( '/[^0-9]/', '', $this->getConfigData('cnpj') ),
						  "emporigem"  => 2,
						  "ceporigem"  => preg_replace( '/[^0-9]/', '', Mage::getStoreConfig('shipping/origin/postcode', $this->getStore())),
						  "cepdestino" => preg_replace( '/[^0-9]/', '', $request->getDestPostcode() ),
						  "cnpjrem"    => preg_replace( '/[^0-9]/', '', $this->getConfigData('cnpj') ),
						  "cnpjdes"    => preg_replace( '/[^0-9]/', '', $cnpjdes),
						  "tipofrete"  => $this->getConfigData('shipping_type'),
						  "peso"       => $Weight,
						  "valornf"    => number_format($request->getPackageValue(), 2, '.', ''),
						  "volume"     => $cart->getItemsCount(),
						  "modal"      => $this->getConfigData('modal_type'));
	//  ================================================  //

	//  Chama o web service  //
	$calFrete = $this->getConfigData('access_type') == 1 ? $this->CalculaFrete() : simplexml_load_file($this->_url_base_web . implode(',', $this->params));

	//  Inicializa o resultado da função  //
    $this->_result = Mage::getModel('shipping/rate_result');

	//  Cria o log da chamada deste método  //
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
	//  ==================================  //
    
    if($calFrete->MSGERRO != "OK"){
        $this->_throwError('specificerrmsg', $calFrete->MSGERRO, __LINE__);  
    }else{
        if($calFrete->TOTALFRETE > (float) 0 ){
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
         
            // record method information
            $method->setMethod($this->_code);

			Mage::log($calFrete->PRAZOENTREGA, null, 'braspress_test.log');
			
			if($this->getConfigData('delivery_time')){
				$prazo = $calFrete->PRAZO;
				switch($prazo){
					case 0:
						$prazomsg = ' entrega imediata';
					break;
					case 1:
						$prazomsg = ' prazo de 1 dia';
					break;
					default:
						$prazomsg = ' prazo de '.$prazo.' dias';
					break;
				}
				
				$method_name .= $this->getConfigData('method_name').$prazomsg;
			}else{
				$method_name .= $this->getConfigData('method_name');
			}

            $method->setMethodTitle($method_name);

			//  Formata o valor do frete para float, caso contrário, não grava os centavos  //
            $method->setPrice((float)preg_replace(array('|[^\d\.,]|', '|\.|', '|,|'), array('', '', '.'), $calFrete->TOTALFRETE));

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
