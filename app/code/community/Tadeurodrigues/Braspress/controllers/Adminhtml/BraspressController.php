<?php
/**
 * Atwix
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

 * @category    Braspress Mod
 * @package     Tadeurodrigues_Braspress
 * @author      Tadeu Rodrigues
 * @copyright   Copyright (c) 2012 Atwix (http://www.atwix.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Tadeurodrigues_Braspress_Adminhtml_BraspressController extends Mage_Adminhtml_Controller_Action
{
	 protected $_url_base_web	=	'http://www.braspress.com.br/cotacaoXml?param=';
	  
    /**
     * Return some checking result
     *
     * @return void
     */
    public function validationAction()
    {
        $result = $this->getRequest()->getParams();
		if($result['cnpj']){
			$cnpj = $result['cnpj'];
		}else{
			$cnpj = '44015477000116';
		}
		
		$params = array(
					'cnpj'			=> $cnpj,
    				'emporigem' 	=> '2',
    				'ceporigem' 	=> '60764310',
    				'cepdestino' 	=> '60764280',
    				'cnpjrem' 		=> '02049076000137',
    				'cnpjdes' 		=> '02049076000137',
    				'tipofrete' 	=> '1',
    				'peso' 			=> '300',
    				'valornf' 		=> '30.00',
    				'volume' 		=> '1',
    				'modal' 		=> 'R',
    			);
		$params = implode(',', $params);
		
		
		$calFrete = simplexml_load_file($this->_url_base_web.$params);
		
		if($calFrete->MSGERRO != 'OK'){
			$status = 'ERROR';
			$msg = $calFrete->MSGERRO;
		}else{
			$status = 'OK';
			$msg = 'Autenticado com sucesso';
		}
		
		if($calFrete->erro){
			$msg = 'CNPJ não autorizado a consulta';
		}
		
		$resultArray = array('cnpj' => $cnpj, 'msg' => $msg, 'status' => $status, 'url' => $this->_url_base_web.$params);
		
        Mage::app()->getResponse()->setBody(json_encode($resultArray));
    }
}