<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;
use stdClass;

require_once(dirname(__FILE__)."/../Database.php");

class QueueModel {
    private const STATUS_PENDING = 'PENDING';
    private const STATUS_PROCESS = 'PROCESS';
    private const STATUS_COMPLETED= 'COMPLETED';

    private $db;

    public function __construct(){
        $this->db = new Database();
    }

    public function add($prefix, $nik){
        $queue_no = 0;

        $queues = $this->db->query("
            select * 
            from queue 
            where queue_prefix = '$prefix'
            order by queue_no DESC 
            limit 1"
        );

        if(count($queues)>0){
            $queue_no = intval($queues[0]->queue_no);
        }
        
        $queue_no++;

        $res = $this->db->execute("
            insert into queue(
                queue_prefix,
                queue_no,
                nik,
                status
            )
            values(
                '$prefix',
                $queue_no,
                '$nik',
                '".self::STATUS_PENDING."'
            )
        ");

        $lastId = $this->db->getLastInsertedId();

        $queues = $this->db->query("
            select * 
            from queue 
            where queue_id = $lastId
        ");

        return count($queues)>0? $queues[0]: null;
    }

    public function getQueue($prefix){
        $queues = $this->db->query("
            select * 
            from queue 
            where queue_prefix = '$prefix'
            order by created_at limit 1
        ");

        $result = null;

        if(count($queues)>0){
            $res = $this->db->execute("
                update queue
                set
                    status = '".self::STATUS_PROCESS."'
                where
                    queue_id = ".$queues[0]->queue_id."
            ");
        }

        return $result;
    }

    public function complete($queue_id){
        $res = $this->db->execute("
            update queue
            set
                status = '".self::STATUS_COMPLETED."'
            where
                queue_id = $queue_id
        ");

        return $res;
    }

    public function reQueue($queue_id){
        $res = $this->db->execute("
            update queue
            set
                status = '".self::STATUS_PENDING."'
            where
                queue_id = $queue_id
        ");

        return $res;
    }

    private function removesCompleted(){
        $res = $this->db->execute("
            delete from queue
            where
                status = '".self::STATUS_COMPLETED."'
        ");

        return $res;
    }
}