<?php
class TadeuRodrigues_Braspress_Model_Source_CnpjDefault
{
    public function toOptionArray()
    {
        return array(
            array('value' => '0', 'label' => 'Cliente'),
            array('value' => '1', 'label' => 'Loja'),
            array('value' => '2', 'label' => 'Default'),
        );
    }
}