<?php
class TadeuRodrigues_Braspress_Model_Source_AccessType
{
    public function toOptionArray()
    {
        return array(
            array('value' => '1', 'label' => 'SOAP'),
            array('value' => '2', 'label' => 'HTTP'),
        );
    }
}