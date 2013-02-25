<?php
class TadeuRodrigues_Braspress_Model_Source_ShippingType
{
    public function toOptionArray()
    {
        return array(
            array('value' => '1', 'label' => 'CIF'),
            array('value' => '2', 'label' => 'FOB'),
        );
    }
}