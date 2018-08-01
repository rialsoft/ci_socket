<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface
{
    protected $clients;
    protected $users=array();

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->CI =& get_instance();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $numRecv    = count($this->clients) - 1;
        $connID     = $from->resourceId;
        $sendMsg    = array();

        echo $msg;
        echo "\n";
        $msg        = base64_decode($msg);
        $strMsg     = json_decode( $msg ,TRUE);
        $frmMsg     = $this->CI->db->escape_str($strMsg['from']);
        $toMsg      = $this->CI->db->escape_str($strMsg['to']);
        $Msg        = $this->CI->db->escape_str($strMsg['msg']);
        $typeMsg    = $this->CI->db->escape_str($strMsg['type']);
        $initMsg    = $this->CI->db->escape_str($strMsg['initMsg']);

        if($initMsg){
            $this->users[] = array(
                                "connID"    => $connID,
                                "frmMsg"    => $frmMsg,
                                "conn"      => $from,
                                );
            
            $sql = "SELECT
                        `id`, 
                        `from`, 
                        `to`, 
                        `msg`, 
                        `status`, 
                        `lup`
                    FROM 
                        `chat_msg` 
                   WHERE `status`=0 AND `to`='$frmMsg'
                   ORDER BY `lup`,`id`;";
            
            $query = $this->CI->db->query($sql);
            
            if ( $query->num_rows() > 0 ){
                foreach( $query->result_array() as $msgKey => $msgRow){
                    $sendMsg[] = array(
                                        "from"  => $msgRow['from'],
                                        "to"    => $msgRow['to'],
                                        "msg"   => $msgRow['msg'],
                                    );
                    }

                $from->send( base64_encode(json_encode($sendMsg)) );

                $sql = "DELETE FROM `chat_msg` WHERE `status`=0 AND `to`='$frmMsg';";
                 $this->CI->db->query($sql);
            }

        }else{
           $userKey =  array_keys( array_column($this->users,'frmMsg'), $toMsg);
           if(count($userKey)>0){
                foreach($userKey as $Keys){
                    if(isset( $this->users[$Keys] )){
                        $userTo  = $this->users[$Keys]["conn"];
                        $sendMsg = array(
                                        array(
                                            "from"  => $frmMsg,
                                            "to"    => $toMsg,
                                            "msg"   => $Msg,
                                        )
                                    );

                        $userTo->send( base64_encode(json_encode($sendMsg)) );
                    }

                }
            }else{
                $sql = "INSERT INTO `chat_msg` (`from`, 
                                                `to`, 
                                                `msg`
                                                )
                                    VALUES  ('$frmMsg', 
                                            '$toMsg', 
                                            '$Msg'
                                            );";
                $this->CI->db->query($sql);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) 
    {   
        $this->clients->detach($conn);
        $connID     = $conn->resourceId;
        $userKey    =  array_search($connID, array_column($this->users,'connID'));
          
        if($userKey !== FALSE){
            if(isset($this->users[$userKey])){
                unset($this->users[$userKey]);
                $this->users = array_merge($this->users);
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    
} 
