<?php
class Dbman
{

    private $conf;

    public $errMsg;

    public function __construct()
    {
        $pm = php_sapi_name();
        $this->conf = $this->_getConf();
        if($this->conf['web_access'] == true && $pm != 'cli'){
            exit('Have no right to access !');
        }
        //$this->conf = $this->_getConf();
        $this->db_lnk = $this->_connect($this->conf['db_host'],$this->conf['username'],$this->conf['password'],$this->conf['database']);

    }

    public function __destruct()
    {
        $this->close();//关闭数据库连接
    }

    protected function _getConf(){
        return require "config.php";
    }

    private function get_schema_file($schema = '')
    {
        $db = array();
        if ($schema) {

        } else {
            $dir = $this->conf['file_path'];
            //echo $dir;
            if (is_dir($dir)) {
                if ($dh = opendir($dir)) {
                    while (($file = readdir($dh)) !== false) {
                        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                            $db[basename($file, ".php")] = require $dir . '/' . $file;
                        }
                    }
                    closedir($dh);
                }
            }
        }
        //var_dump($db);die;
        if ($db) {
            return $db;
        }
        return false;

    }

    private function get_create_table_sql($tablename = '',$arr = array())
    {
        //var_dump($arr);
        if ($arr && $arr['fields']) {
            foreach ($arr['fields'] as $k => $v) {
                if ($v && is_array($v)) if ($v['name']) $rows[] = '`' . $v['name'] . '` ' . $this->get_column_define($v);
            }
            $sql = 'CREATE TABLE `'.$tablename."` (\n\t".implode(",\n\t",$rows)."\n)";
            $engine = isset($arr['engine'])?$arr['engine']:'InnoDB';
            $sql .= 'ENGINE = '.$engine.' DEFAULT CHARACTER SET utf8;';
            //todo if(isset($arr['comment']) && $arr['comment'])
            $sql_arr[] = $sql;
            if($arr['index'] && is_array($arr['index'])){
                foreach($arr['index'] as $ik=>$iv){
                    $sql_arr[] = $this->add_index_sql($tablename,$iv);
                }
            }
            return $sql_arr;
        }
        return false;
    }

    private function __diff($new_rows=array(),$old_info=array()){

    }

    private function get_update_table_sql($tablename = '',$new_db = array(),$old_db = array()){
        $_tableInfo = unserialize($old_db['tableinfo']);

        $_tableInfoOption = $_tableInfo['fields'];
        $rows = array();
        foreach($new_db['fields'] as $dbk=>$dbinfo){

            if(!isset($_tableInfoOption[$dbk]) && !isset($_tableInfoOption[$dbk]['name'])){
                //echo $dbinfo['name'].'添加字段'.$dbk.'  |  '.$dbinfo['name']."<br/>";
                $rows[] = $this->add_column($tablename,$dbinfo);
            }elseif(isset($_tableInfoOption[$dbk]['name']) && $dbinfo['name'] == $_tableInfoOption[$dbk]['name']){
                $oldFieldsInfo = $_tableInfoOption[$dbk];
                $update = false;
                if($dbinfo['type'] != $oldFieldsInfo['type']){
                    $update = true;
                }
                if($dbinfo['notnull'] != $oldFieldsInfo['notnull']){
                    $update = true;
                }
                if($dbinfo['default'] != $oldFieldsInfo['default']){
                    $update = true;
                }
                if($dbinfo['primary'] != $oldFieldsInfo['primary']){
                    $update = true;
                }
                if($dbinfo['autoinc'] != $oldFieldsInfo['autoinc']){
                    $update = true;
                }
                if($update) $rows[] = $this->update_column($tablename,$dbinfo);
            }

        }
        $_tableInfoIndex = $_tableInfo['index'];
        foreach((array)$new_db['index'] as $dbik=>$dbiv){

            if(!isset($_tableInfoIndex[$dbik]) && !isset($_tableInfoIndex[$dbik]['name'])){
                //添加索引
                $rows[] = $this->add_index_sql($tablename,$dbiv);
            }elseif(isset($_tableInfoIndex[$dbik]['name']) && $dbiv['name'] == $_tableInfoIndex[$dbik]['name']){
                //更新索引
                $oldIndexInfo = $_tableInfoIndex[$dbik];
                $update = false;
                if($dbiv['type'] != $oldIndexInfo['type']){
                    $update = true;
                }
                if($dbiv['fields'] != $oldIndexInfo['fields']){
                    $update = true;
                }
                if($dbiv['method'] != $oldIndexInfo['method']){
                    $update = true;
                }
                if($update){
                    $rows[] = $this->drop_index_sql($tablename,$dbiv['name']);
                    $rows[] = $this->add_index_sql($tablename,$dbiv);//add_index_sql
                }
            }
        }
        return $rows;
    }
    private function add_index_sql($tablename='',$info=array()){
        $sql = "ALTER TABLE `{$tablename}` ADD ";
        $type = isset($arr['type']) && $arr['type'] ? $arr['type'] : 'normal';
        if($type == 'normal'){
            $sql .= ' INDEX '.$info['name'];
        }elseif($type == 'unique'){
            $sql .= ' UNIQUE '.$info['name'];
        }elseif($type == 'primary'){
            $sql .= ' PRIMARY KEY ';
        }
        if(is_array($info['fields']) && $info['fields']){
            $sql .= '(`'.implode('`,`',$info['fields']).'`)';
        }else{
            $sql .= '(`'.$info['fields'].'`)';
        }
        return $sql;
    }

    protected function get_column_define($v)
    {
        $str = '';
        if (isset($v['type']) && $v['type']) {
            $str .= ' ' . $v['type'];
        } else {
            $str .= ' varchar(255)';
        }

        if (isset($v['notnull']) && $v['notnull']) {
            $str .= ' not null';
        }

        if (isset($v['primary']) && $v['primary']) {
            $str .= ' PRIMARY KEY';
        }
        if (isset($v['autoinc']) && $v['autoinc']) {
            $str .= ' AUTO_INCREMENT';
        }

        if (isset($v['default'])) {
            if ($v['default'] === null) {
                $str .= ' default null';
            } elseif (is_string($v['default'])) {
                $str .= ' default \'' . $v['default'] . '\'';
            } else {
                $str .= ' default ' . $v['default'];
            }
        }
        if (isset($v['comment'])) {
            $str .= ' comment \'' . $v['comment'] . '\'';
        }
        return $str;
    }

    public function maintain(){

        $sys_table = $this->conf['sys_table'];
        if(!$this->table_exists($sys_table)){
            echo PHP_EOL."You should use the init method,And use the --data parameter!".PHP_EOL;
            exit();
        }
        $this->update();
        $this->delete();
        if($this->errMsg && is_array($this->errMsg)) {
            foreach((array)$this->errMsg as $err){
                echo  ' | err: ' .$err.PHP_EOL;
            }
            exit('Run failed .!');
        }
    }

    public function delete(){
        $db_arr = $this->get_schema_file();
        $tables = $this->getTableData();
        foreach((array)$tables as $v){

            if(isset($db_arr[$v['tablename']]) && $db_arr[$v['tablename']]){
                $newTableInfo = $db_arr[$v['tablename']];
                $oldTableInfo = unserialize($v['tableinfo']);
                $update_log = false;
                foreach($oldTableInfo['fields'] as $fields_k=>$fields_v){
                    if(!isset($newTableInfo['fields'][$fields_v['name']])){
                        //删除字段
                        $update_log = true;
                        $sql = $this->drop_column($v['tablename'],$fields_v);
                        $this->execsql($sql,$errMsg);
                    }
                }
                foreach((array)$oldTableInfo['index'] as $index_k=>$index_v){
                    if(!isset($newTableInfo['index'][$index_v['name']])){
                        //删除索引
                        $update_log = true;
                        $sql = $this->drop_index_sql($v['tablename'],$index_v['name']);
                        $this->execsql($sql,$errMsg);
                    }
                }
                if($update_log){
                    $log_sql = $this->setTableInfo($v['tablename'],$newTableInfo);
                    $this->execsql($log_sql,$errMsg);
                }
            }
        }
        echo PHP_EOL."Deleting completed.!".PHP_EOL;
    }

    public function update(){
        $db_arr = $this->get_schema_file();
        $default_v = $this->conf['default_v'];
        if($db_arr && is_array($db_arr)){
            //$this->execsql('start transaction');
            foreach($db_arr as $k=>$v){
                $tableInfo = $this->getTableInfo($k);
                $v['version'] = (isset($v['version']) && !empty($v['version'])) ? $v['version'] : $default_v;
                if($tableInfo && $tableInfo['tablename'] && $tableInfo['tableinfo'] && $v['version'] != '-1'){
                    //更新表
                    $oldInfo = md5($tableInfo['tableinfo']);
                    $newInfo = md5(serialize($v));
                    if($oldInfo != $newInfo){
                        $sql_arr = $this->get_update_table_sql($k,$v,$tableInfo);
                        if($sql_arr) {
                            $log_sql = $this->setTableInfo($k,$v);
                            $this->execsql($log_sql,$errMsg);
                            foreach($sql_arr as $sql){
                                $rs = $this->execsql($sql,$errMsg);
                            }
                        }
                    }
                }elseif($tableInfo && $v['version'] == '-1'){
                    //删除表
                    $log_sql = $this->delTableInfo($k,$v);
                    $this->execsql($log_sql,$errMsg);
                    $sql = $this->drop_table($k);
                    if($sql && $sql != false){
                        $rs = $this->execsql($sql,$errMsg);
                    }
                }elseif(empty($tableInfo)&& !$this->table_exists($k) && $v['version'] != '-1'){
                    //创建表
                    $log_sql = $this->setTableInfo($k,$v);
                    $this->execsql($log_sql,$errMsg);
                    $sql_arr = $this->get_create_table_sql($k,$v);
                    if($sql_arr) foreach($sql_arr as $sql){
                        $rs = $this->execsql($sql,$errMsg);
                    }
                }
            }
        }

        echo PHP_EOL.'Update complete.!'.PHP_EOL;
    }

    public function init($init=false){
        $sys_table = $this->conf['sys_table'];
        if($this->table_exists($sys_table)){
            echo PHP_EOL."Failure: Repeat the initialization".PHP_EOL;
            exit();
        }
        $sql = "CREATE TABLE `{$sys_table}`( `md5tablename` char(32) not null PRIMARY KEY default '' , `tablename` varchar(32) not null default '', `version` varchar(15) not null default '1.0', `tableinfo` longtext , `refresh_time` char(14) not null default 'null' )ENGINE = innodb DEFAULT CHARACTER SET utf8; ";
        $this->execsql($sql);
        if($init && $init == true){
            $database = $this->conf['database'];
            $tables   = $this->getTables($database);
            foreach ($tables as $table) {
                $info = $this->getFields($table);
                $log_sql = $this->setTableInfo($table,$info);
                $this->execsql($log_sql,$errMsg);
            }
        }
        echo PHP_EOL."The initial complete".PHP_EOL;
    }

    private function table_exists($tablename=''){
        $database = $this->conf['database'];
        $sql = "select TABLE_NAME AS tablename from INFORMATION_SCHEMA.TABLES where TABLE_SCHEMA='".$database."' and TABLE_NAME='".$tablename."'";
        return $this->getRow($sql);
    }
    private function getTableInfo($tablename=''){
        $tablename = md5($tablename);
        $sys_table = $this->conf['sys_table'];
        $sql = "select * from `{$sys_table}` where md5tablename='".$tablename."' limit 1";
        return $this->getRow($sql);
    }
    private function getTableData($tablename=''){
        $sys_table = $this->conf['sys_table'];
        $sql = "select * from `{$sys_table}`";
        return $this->getList($sql);
    }
    private function setTableInfo($tablename='',$tableinfo=''){
        $sys_table = $this->conf['sys_table'];
        $default_v = $this->conf['default_v'];
        $sql = "REPLACE INTO `{$sys_table}`(md5tablename, tablename, version, tableinfo, refresh_time) VALUES('".md5($tablename)."','".$tablename."','".($tableinfo['version']?$tableinfo['version']:$default_v)."','".addslashes(serialize($tableinfo))."','".date('YmdHis')."')";
        return $sql;
    }
    private function delTableInfo($tablename='',$tableinfo=''){
        $sys_table = $this->conf['sys_table'];
        //$sql = "REPLACE INTO sys_schema_info(md5tablename, tablename, version, tableinfo, refresh_time) VALUES('".md5($tablename)."','".$tablename."','".($tableinfo['version']?$tableinfo['version']:'')."','".addslashes(serialize($tableinfo))."','".date('YmdHis')."')";
        $sql = "delete from `{$sys_table}` where md5tablename='".md5($tablename)."'";
        return $sql;
    }
    private function drop_table($tablename=''){
        $sql = "DROP TABLE IF EXISTS {$tablename}";
        //$rs = $this->execsql($sql, $errMsg);
        return $sql;
    }
    private function drop_column($tablename='',$column=''){
        $sql = "alter table `{$tablename}` drop column `{$column['name']}`";
        //$rs = $this->execsql($sql, $errMsg);
        return $sql;
    }
    private function add_column($tablename='',$column=''){
        $sql = "alter table `{$tablename}` add column  ".$column['name'].' '.$this->get_column_define($column);
        //$rs = $this->execsql($sql, $errMsg);
        return $sql;
    }
    private function update_column($tablename='',$column=''){
        $sql = "alter table `{$tablename}` MODIFY COLUMN `".$column['name'].'` '.$this->get_column_define($column);
        //$rs = $this->execsql($sql, $errMsg);
        return $sql;
    }

    private function drop_index_sql($tablename='',$index=''){
        $sql = "ALTER TABLE `{$tablename}` DROP INDEX `{$index}`";
        return $sql;
    }



    protected function _connect($host,$user,$passwd,$dbname){
        $lnk = @mysql_connect($host,$user,$passwd) or die("Unable to connect to the database");
        mysql_select_db( $dbname, $lnk );
        return $lnk;
    }
    protected function execsql($sql='',&$errMsg=''){
        //return false;
        $db_lnk = $this->db_lnk;
        $this->_debug_log($sql);
        if($rs = mysql_query($sql,$db_lnk)){
            return array('rs'=>$rs,'sql'=>$sql);
        }else{
            $this->errMsg[] = mysql_error($db_lnk);
            return false;
        }

    }

    protected function getRow($sql='',&$errMsg=''){
        $rs = $this->execsql($sql, $errMsg);
        if($rs['rs']){
            $data = array();
            while($row = mysql_fetch_assoc($rs['rs'])){
                $data[]=$row;
            }
            mysql_free_result($rs['rs']);
            return (!empty($data) && $data) ? $data[0] : array();
        }else{
            return false;
        }
    }

    protected function getList($sql='',&$errMsg=''){
        $rs = $this->execsql($sql, $errMsg);
        if($rs['rs']){
            $data = array();
            while($row = mysql_fetch_assoc($rs['rs'])){
                $data[]=$row;
            }
            mysql_free_result($rs['rs']);
            return $data;
        }else{
            return false;
        }
    }

    protected function close(){
        if($this->db_lnk && mysql_close($this->db_lnk)){
            $this->db_lnk = null;
            return true;
        }
        return false;
    }

    protected function _debug_log($data){
        if(!$this->conf['debug_log']){
            return false;
        }
        error_log(date('Y-m-d H:i:s').' err '.$data.PHP_EOL,3,'/tmp/dbman.'.date('Y-m-d').'.logs');
    }

    protected function buildDataBaseSchema($tables, $db)
    {
        if ('' == $db) {
            $dbName = $this->conf['database'];
        } else {
            $dbName = $db;
        }
        $backups_path = $this->conf['backups_path'];
        //var_export($backups_path);die;
        foreach ($tables as $table) {
            $content = '<?php ' . PHP_EOL . 'return ';
            $info    = $this->getFields($table);
            $content .= var_export($info, true) . ';';
            file_put_contents($backups_path . $table . '.php', $content);
        }
    }

    protected function getFields($tableName)
    {
        $tableName = '`' . $tableName . '`';
        $sql = 'SHOW COLUMNS FROM ' . $tableName;
        $result = $this->getList($sql);
        $info   = [];
        if ($result && is_array($result)) {
            foreach ($result as $key => $val) {
                $val                 = array_change_key_case($val);
                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => (bool) ('' === $val['null']),
                    'default' => $val['default'],
                    'primary' => (strtolower($val['key']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                ];
            }
        }
        $data['fields'] = $info;
        $data['index'] = array();
        $data['version'] = '1.0';
        $data['engine'] = 'innodb';
        $data['comment'] = $tableName;
        return $data;
    }

    protected function getTables($dbName = '')
    {

        $sql = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES ';
        $result = $this->getList($sql);
        $info   = [];
        //var_export($result);die;
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    public function backups(){
        $database = $this->conf['database'];
        $tables   = $this->getTables($database);
        $this->buildDataBaseSchema($tables,$database);
        echo PHP_EOL.'The backup data table'.PHP_EOL;
    }
}

