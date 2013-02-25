<?php
class TadeuRodrigues_Braspress_Model_Source_ModalType
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'R', 'label' => 'Rodoviário'),
            array('value' => 'A', 'label' => 'Aéreo'),
        );
    }
}
