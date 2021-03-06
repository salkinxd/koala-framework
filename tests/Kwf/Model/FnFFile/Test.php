<?php
/**
 * @group Model
 * @group Model_FnFFile
 * @group slow
 */
class Kwf_Model_FnFFile_Test extends Kwf_Test_TestCase
{
    public function testSimple()
    {
        $m = Kwf_Model_Abstract::getInstance('Kwf_Model_FnFFile_Model');
        $m->setData(array());
        $row = $m->createRow();
        $row->id = 1;
        $row->test = 'foo';
        $row->save();

        $cmd = "php ".KWF_PATH."/bootstrap.php test forward --controller=kwf_model_Fn-f-file_test --action=read";
        $this->assertEquals('foo', exec($cmd));
    }

    public function testReload()
    {
        $m = Kwf_Model_Abstract::getInstance('Kwf_Model_FnFFile_Model');
        $m->setData(array());
        $row = $m->createRow();
        $row->id = 1;
        $row->test = 'foo';
        $row->save();

        $cmd = "php ".KWF_PATH."/bootstrap.php test forward --controller=kwf_model_Fn-f-file_test --action=write";
        system($cmd);
        clearstatcache();

        $row = $m->getRow(1);
        $this->assertEquals('overwritten', $row->test);
    }

    public function testTwoParalellProcesses()
    {
        $descriptorspec = array(
            1 => array("pipe", "w"),
        );
        $cmd = "php ".KWF_PATH."/bootstrap.php test forward --controller=kwf_model_Fn-f-file_test --action=read-after-delay";
        $process = proc_open($cmd, $descriptorspec, $pipes);
        $this->assertTrue(is_resource($process));

        $m = Kwf_Model_Abstract::getInstance('Kwf_Model_FnFFile_Model');
        $m->setData(array());
        $row = $m->createRow();
        $row->test = 'bar';
        $row->save();

        $this->assertEquals('bar', stream_get_contents($pipes[1]));
        proc_close($process);
    }
}
