<?php
class Kwc_Form_Field_Radio_Component extends Kwc_Form_Field_Select_Component
{
    public static function getSettings($param = null)
    {
        $ret = parent::getSettings($param);
        $ret['componentName'] = trlKwfStatic('Form.Radio');
        return $ret;
    }

    protected function _getFormField()
    {
        $ret = new Kwf_Form_Field_Radio($this->getData()->componentId);
        $ret->setFieldLabel($this->getRow()->field_label);
        if ($this->getRow()->label_width) $ret->setLabelWidth($this->getRow()->label_width);
        $ret->setOutputType($this->getRow()->output_type);
        $ret->setAllowBlank(!$this->getRow()->required);
        $ret->setHideLabel($this->getRow()->hide_label);
        $values = array();
        $s = new Kwf_Model_Select();
        $s->order('pos');
        foreach ($this->getRow()->getChildRows('Values', $s) as $i) {
            $values[$i->value] = $i->value;
        }
        $ret->setValues($values);
        if ($this->getRow()->label_position_above) {
            $ret->setLabelPosition('above');
        }
        return $ret;
    }
}
