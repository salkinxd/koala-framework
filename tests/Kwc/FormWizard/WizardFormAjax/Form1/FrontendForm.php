<?php
class Kwc_FormWizard_WizardFormAjax_Form1_FrontendForm extends Kwf_Form
{
    protected $_model = 'Kwc_FormWizard_WizardFormAjax_Form1_Model';

    protected function _init()
    {
        $this->add(new Kwf_Form_Field_TextField('text', trlStatic('Text')));
        parent::_init();
    }
}
