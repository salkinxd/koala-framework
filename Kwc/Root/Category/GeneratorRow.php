<?php
class Kwc_Root_Category_GeneratorRow extends Kwf_Model_Tree_Row
{
    protected function _beforeInsert()
    {
        parent::_beforeInsert();
        if (!$this->is_home) $this->is_home = 0;
        if (!$this->visible) $this->visible = 0;
        if (!$this->pos) $this->pos = 1;

        //fill parent_subroot_id cache
        $c = Kwf_Component_Data_Root::getInstance()
            ->getComponentById($this->parent_id, array('ignoreVisible'=>true));
        $this->parent_subroot_id = $c->getSubroot()->componentId;
    }

    protected function _beforeUpdate()
    {
        parent::_beforeUpdate();
        if ($this->is_home && !$this->visible) {
            throw new Kwf_ClientException(trlKwf('Cannot set Home Page invisible'));
        }
        if (in_array('parent_id', $this->getDirtyColumns()) && $this->getCleanValue('parent_id')) {
            $oldSubroot = Kwf_Component_Data_Root::getInstance()
                ->getComponentById($this->getCleanValue('parent_id'), array('ignoreVisible'=>true))
                ->getSubroot();
            $newSubroot = Kwf_Component_Data_Root::getInstance()
                ->getComponentById($this->parent_id, array('ignoreVisible'=>true))
                ->getSubroot();
            if ($oldSubroot != $newSubroot) {
                throw new Kwf_Exception_Client(trlKwf("Can't move Page to other Subroot"));
            }
        }
        if (in_array('filename', $this->getDirtyColumns())) {
            $this->_updateHistory('filename');
        }
        if (in_array('parent_id', $this->getDirtyColumns())) {
            $this->_updateHistory('parent_id');
        }
    }

    private function _updateHistory($cleanValueColumn)
    {
        $model = Kwf_Component_Data_Root::getInstance()
            ->getComponentById($this->id, array('ignoreVisible'=>true))
            ->generator->getHistoryModel();
        $row = $model->createRow(array('page_id' => $this->id));
        if ($cleanValueColumn == 'parent_id') {
            $row->parent_id = $this->getCleanValue('parent_id');
        } else {
            $row->parent_id = $this->parent_id;
        }
        if ($cleanValueColumn == 'filename') {
            $row->filename = $this->getCleanValue('filename');
        } else {
            $row->filename = $this->filename;
        }
        $row->save();
    }

    public function getComponentsDependingOnRow()
    {
        $ret = array();

        foreach (Kwc_Admin::getDependsOnRowInstances() as $a) {
            foreach ($a->getComponentsDependingOnRow($this) as $i) {
                if ($i) {
                    $ret[] = $i;
                }
            }
        }

        //unterseiten
        foreach ($this->getChildNodes() as $c) {
            $ret = array_merge($ret, $c->getComponentsDependingOnRow());
        }

        //rekursive links ignorieren
        foreach ($ret as $k=>$r) {
            while ($r) {
                if ($r->componentId == $this->id) {
                    unset($ret[$k]);
                    break;
                }
                $r = $r->parent;
            }
        }
        return array_values($ret);
    }


    public function duplicate(array $data = array())
    {
        $ret = parent::duplicate($data);
        $ret->custom_filename = false;
        $ret->save();
        return $ret;
    }
}
