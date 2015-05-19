#!/usr/bin/php -q
<?php
/**
 * @Author: Zied Chabaane
 * @Date:   2015-04-15 17:47:40
 * @Last Modified by:   Zied Chabaane
 * @Last Modified time: 2015-04-20 15:51:00
 * @Version 0.2
 * EXAMPLE OF USE :
 * CONF:
 * sudo apt-get install tnef 
 * cd /home/filter && mkdir -p Result
 * chown -R vmail:vmail  /home/filter
 * chmod -R a+rw /home/filter
 * chmod -R a+rwx /home/filter/Result
 * add lines to /etc/postfix/master.cf:
 * smtp      inet  n       -       -       -       -       smtpd
 *      -o content_filter=filterService:dummy
 * filterService    unix  -       n       n       -       1       pipe
 *      flags=Xq user=vmail argv=php -q /home/filter/postfix.php /home/filter noreply@domain.com ${sender} ${recipient}
 *
 * OPTTIONAL LOCAL DOMAIN  DNS :
 * add @domain.com IP to /etc/hosts
 * add smtp_hosts_lookup = native TO /etc/postfix/main.cf
 * modify to hosts: hosts files dns IN /etc/nsswitch.conf
 */


include_once "class.phpmailer.php";
$home = $argv[1];
$from = $argv[2];
$sender = $argv[3];
$receipient = $argv[4];
$b = fopen($home."/log","a");
fwrite($b,date("Y-m-d H:i:s").": INCOMING MAIL FROM ".$sender." TO ".$receipient."\n"); 
$now = time();
$dir = $home."/Result/".$now;
//shell_exec("mkdir -p ".$dir);
$nameOutFile=$dir."/out.eml";

//copy the source of mail recieved to file named "win"; 

$email = file_get_contents('php://stdin');
$winame = "/win_".$now;
$fileWin = fopen($home.$winame, "a");
$err = print_r(error_get_last(),true);
fwrite($b,date("Y-m-d H:i:s").": ERR : ".$err."\n"); 

fwrite($fileWin, $email);
$nameSourceFile=$home.$winame;

if (strpos($email,"winmail.dat") == false) {  
  shell_exec("chmod a+rw ".$nameSourceFile);
  //$cmd = "sendmail -G -i ".$receipient." ".$sender." < ".$nameSourceFile;
  $cmd = "/usr/lib/dovecot/deliver -f \"".$sender."\" -d \"".$receipient."\" -p ".$nameSourceFile;
  $out = shell_exec($cmd);
  fwrite($b,date("Y-m-d H:i:s").": FORWARDING MAIL TO ".$receipient."\n");
  fwrite($b,date("Y-m-d H:i:s").": DOVECOT OUTPUT : ".$out."\n"); 
  shell_exec("rm ".$nameSourceFile);
 
}
else {
//extract file winmail.dat and sender path from source code of email
$out = shell_exec("mkdir -p ".$dir);
fwrite($b,date("Y-m-d H:i:s").": MKDIR OUTPUT : ".$out."\n"); 
get_tnef_part($nameOutFile, $nameSourceFile);
$senderMail=get_sender($nameSourceFile);
$subject=get_subject($nameSourceFile);
//extract all documents from winail.dat in folder Result

$out = shell_exec("tnef -C ".$dir." -f $nameOutFile");
fwrite($b,date("Y-m-d H:i:s").": TNEF OUTPUT : ".$out."\n"); 
if ($handle = opendir($dir)) {

        $nbFile=0;
    while (false !== ($entry = readdir($handle))) {

        if ($entry != "." && $entry != "..") {
                $files[$nbFile]=$dir."/".$entry;
                fwrite($b,date("Y-m-d H:i:s").": WINMAIL : ".$files[$nbFiles]."\n"); 
            $nbFile++;

        }
    }
    print_r ($files);
    closedir($handle);
} else {
        //error open folder
}

//------------------Send Reply with Attachement---------------------

$mail = new PHPMailer;
$mail->From = from;
$mail->FromName = 'NOREPLY';
$mail->addAddress($sender, ' ');      // Add a recipient
$mail->addReplyTo($from, 'Admin');



for($x=0;$x<count($files);$x++){
        if (strcmp($files[$x],$dir."/out.eml") !== 0)
        {
               $mail->addAttachment($files[$x]);
              
        }
}

$mail->Subject = "{$subject}";
$mail->Body    = 'windmail reply';
if(!$mail->send()) {
  fwrite($b,date("Y-m-d H:i:s").": MAIL NOT SENT TO ".$sender. " ERR : ".$mail->ErrorInfo."\n");

  
} else {
  fwrite($b,date("Y-m-d H:i:s").": SENDING REPLY WINMAIL MAIL TO ".$sender."\n"); 
  $out = shell_exec("rm ".$home."/".$winame);
  //$out = shell_exec("rm ".$dir."/*");
  $out = shell_exec("rm -rf ".$dir);

}
fclose($b);
}

//------------------End Send ---------------------


function get_tnef_part($nameOutFile, $nameSourceFile) {
        //base64_decode

        $out = fopen($nameOutFile,"a"); 
//        $content = fopen($nameOutFile.".cont","a");
        $handle = fopen($nameSourceFile, "r");
        $counter = 0;
        $end = 0;
        $continue = false;
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line,"winmail.dat") !== false) {                                     
                        $continue = true;
                }
                if ($continue===true) {
                        if (strlen($line)==1) {
                                $counter++;
                               // fwrite($content,strlen($line));
                        }
                        else {
                            if ($counter == 1) {
                                if (ereg("--(.*)--",$line) == true) {
                                        $end = 1;
                                        //fwrite($content,"\nEND!!");
                                        break;
                                }

                                if ((strpos($line,"Attachment") == false) && (strpos($line,"Content")== false)) {
                                	fputs($out,base64_decode($line));
                                	//fwrite($content,$line);
                                }
                            }
                        }
                }

                if (($counter > 1) || ($end > 0)) break;
            }
            fclose($out);
            fclose($handle);
        } else {
            // error opening the file.
        } 
}

function get_sender($win) {


        $handle = fopen($win, "r");
        //$counter = 0;
        $continue = false;
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line,"From:") !== false) {                                       
                        $sender= strstr($line,'<');
                        break;
                }
            }

        } else {
            // error opening the file.
        } 
        $res=strtr($sender,'>',' ');
        $mail=strtr($res,'<', ' ');
	fclose($handle);

        return trim($mail);   
}

function get_subject($win) {


        $handle = fopen($win, "r");
        //$counter = 0;
        $continue = false;
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (strpos($line,"Subject:") !== false) {
                        $subject= strstr($line,':');
                        break;
                }
            }

        } else {
            // error opening the file.
        } 
        $res=preg_replace('/:/',' ',$subject,1);
        fclose($handle);

        return trim($res);   
}


?>
