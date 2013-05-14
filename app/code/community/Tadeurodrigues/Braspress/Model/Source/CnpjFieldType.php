<?php
class TadeuRodrigues_Braspress_Model_Source_CnpjFieldType
{
    public function toOptionArray()
    {
        return array(
            array('value' => '1', 'label' => 'Tax/Vat'),
            array('value' => '2', 'label' => 'CPF/CNPJ'),
        );
    }
}
