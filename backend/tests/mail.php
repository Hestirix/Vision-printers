<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/core.php';
require dirname(__DIR__) . '/src/mail.php';
function ensure(bool $condition, string $label): void { if (!$condition) throw new RuntimeException('FAIL: '.$label); }
$config = ['mode'=>'mock','ack'=>true]; // No SMTP settings; never call send().
$reference='0123456789abcdef';
$contact=['name'=>'Synthetic Customer','email'=>'customer@example.com','phone'=>'','subject'=>'Test <subject>','message'=>'Test <script>content</script>'];
$quote=['name'=>'Synthetic Customer','email'=>'customer@example.com','phone'=>'+265999277733','service'=>Vision\SERVICES[0],'quantity'=>'12','description'=>'Artwork for local testing','date'=>'2027-01-15'];
$tmp=tempnam(sys_get_temp_dir(),'vp-mime-');
try {
    $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII=');
    file_put_contents($tmp,$bytes);
    $file=Vision\attachment(['error'=>UPLOAD_ERR_OK,'name'=>'sample.png','tmp_name'=>$tmp],false);
    foreach (['contact'=>$contact,'quote'=>$quote] as $kind=>$data) {
        $data=Vision\validate($kind,$data);
        $mail=Vision\prepareInquiry($config,$kind,$data,$kind==='quote'?$file:null,$reference);
        ensure($mail->getToAddresses()===[[Vision\RECIPIENT,'']], 'sole business recipient');
        ensure(Vision\RECIPIENT==='sales@visionprintersmw.com','fixed sales address');
        ensure(!$mail->getCcAddresses() && !$mail->getBccAddresses(),'no CC/BCC');
        ensure(array_values($mail->getReplyToAddresses())===[[$data['email'],$data['name']]],'validated customer Reply-To');
        ensure($mail->From==='preview@visionprintersmw.com','business-controlled mock sender');
        foreach ($data as $value) if($value!=='') ensure(str_contains($mail->AltBody,$value),'all submitted fields in plain body');
        ensure(!str_contains($mail->Body,'<script>'),'escaped HTML');
        ensure($mail->preSend(),'MIME generated without sending');
        $mime=$mail->getSentMIMEMessage();
        ensure(str_contains($mime,'multipart/alternative'),'HTML and plain text alternatives');
        ensure(str_contains($mime,base64_encode($mail->AltBody)) || str_contains(preg_replace('/\s/','',$mime),base64_encode($mail->AltBody)),'encoded plain body');
        if($kind==='quote') {
            ensure(str_contains($mime,'image/png'),'attachment MIME');
            ensure(str_contains($mime,'artwork.png'),'safe attachment filename');
            ensure(str_contains(preg_replace('/\s/','',$mime),base64_encode($bytes)),'attachment payload');
        } else ensure(!$mail->getAttachments(),'contact has no attachment');
        $result=Vision\deliver($config,$kind,$data,$kind==='quote'?$file:null,$reference);
        ensure($result['status']==='mocked' && $result['acknowledgment']==='mocked','mock transport result');
    }
    $ack=Vision\prepareAcknowledgment($config,$contact['email'],$reference);
    ensure($ack->getToAddresses()===[[$contact['email'],'']],'ack only to customer');
    ensure(!$ack->getAttachments() && !$ack->getCcAddresses() && !$ack->getBccAddresses(),'ack contains no artwork or extra recipient');
    ensure(str_contains($ack->AltBody,$reference) && !str_contains($ack->AltBody,$contact['message']),'ack reference without reflected content');
    ensure($ack->preSend(),'ack MIME prepared without sending');
} finally { unlink($tmp); }
echo "PASS: contact/quote MIME, sole business recipient, Reply-To, escaped HTML/plain text, attachment payload and acknowledgments. No send() invoked.\n";
