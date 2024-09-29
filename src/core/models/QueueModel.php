<?php
namespace biometric\src\core\models;

use biometric\src\core\Database;
use stdClass;

require_once(dirname(__FILE__)."/../Database.php");
require_once(dirname(__FILE__)."/PersonModel.php");

class QueueModel {
    private const STATUS_PENDING = 'PENDING';
    private const STATUS_PULLED = 'PULLED';
    private const STATUS_PROCESS = 'PROCESS';
    private const STATUS_COMPLETED= 'COMPLETED';

    private $db;

    public function __construct(){
        $this->db = new Database();
    }

    public function add($prefix, $nik): stdClass{
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

    public function find($queue_id): stdClass{
        $queues = $this->db->query("
            select * 
            from queue 
            where queue_id = $queue_id
        ");

        if(count($queues)>0){
            $result = json_decode(json_encode($queues[0]), true);
            $result['queue_code'] = $this->getQueueCode($result);
            $result['person'] = (new PersonModel())->get($result['nik']);
            $result = json_decode(json_encode($result));

            return $result;
        }

        return null;
    }

    public function findByNik($nik): stdClass{
        $queues = $this->db->query("
            select * 
            from queue 
            where nik = '$nik'
        ");

        if(count($queues)>0){
            $result = json_decode(json_encode($queues[0]), true);
            $result['queue_code'] = $this->getQueueCode($result);
            $result['person'] = (new PersonModel())->get($result['nik']);
            $result = json_decode(json_encode($result));

            return $result;
        }

        return null;
    }

    public function pullQueue(string $prefix){
        $queues = $this->db->query("
            select * 
            from queue 
            where 
                queue_prefix = '$prefix'
                and status = '".self::STATUS_PENDING."'
            order by 
                created_at asc,
                updated_at desc
            limit 1
        ");

        $result = null;

        if(count($queues)>0){
            $result = json_decode(json_encode($queues[0]), true);
            $result['queue_code'] = $this->getQueueCode($result);
            $result['person'] = (new PersonModel())->get($result['nik']);
            $result = json_decode(json_encode($result));

            $res = $this->updateStatus($result->queue_id, self::STATUS_PULLED);
        }

        return $result;
    }

    public function process($queue_id): bool{
        $queues = $this->db->query("
            select * 
            from queue 
            where 
                queue_id = '$queue_id'
                and status = '".self::STATUS_PULLED."'
            limit 1
        ");

        if(count($queues)>0){
            $res = $this->updateStatus($queue_id, self::STATUS_PROCESS);

            return $res;
        }

        return false;
    }

    public function complete($queue_id){
        return $this->updateStatus($queue_id, self::STATUS_COMPLETED);
    }

    public function reEnqueue($queue_id){
        return $this->updateStatus($queue_id, self::STATUS_PENDING);
    }

    private function updateStatus($queue_id, $status){
        $res = $this->db->execute("
            update queue
            set
                status = '$status'
            where
                queue_id = $queue_id
        ");

        return $res;
    }

    private function getQueueCode($queue){
        if(!empty($queue)){
            $queue = json_decode(json_encode($queue));
            $code = $queue->queue_prefix.sprintf('%04d', $queue->queue_no);

            return $code;
        }

        return null;
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