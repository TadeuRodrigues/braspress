<?php
class TadeuRodrigues_Braspress_Model_Source_MeasureType
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'm', 'label' => 'Metros (m)'),
            array('value' => 'dm', 'label' => 'Decímetros (dm)'),
            array('value' => 'cm', 'label' => 'Centímetros (cm)'),
            array('value' => 'mm', 'label' => 'Milímetros (mm)'),
        );
    }
}