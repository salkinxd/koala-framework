<?php
/**
 * @package Model
 * @subpackage Expr
 */
interface Kwf_Model_Select_Expr_Interface
{
    public function validate();
    public function getResultType();
    public function toArray();
    public static function fromArray(array $data);
}
