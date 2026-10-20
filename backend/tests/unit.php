<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/core.php';
use function Vision\validate;
use function Vision\attachment;
use function Vision\message;
use function Vision\rateLimit;
function check(bool $condition, string $label): void { if (!$condition) throw new RuntimeException('FAIL: '.$label); }
function rejected(callable $fn, string $field): void {
    try { $fn(); } catch (Vision\InputError $e) { check(isset($e->fields[$field]), $field); return; }
    throw new RuntimeException('Expected rejection: '.$field);
}
$contact = ['name'=>'Test Customer','email'=>'review@example.com','phone'=>'','subject'=>'Print inquiry','message'=>'Hello <script>alert(1)</script>'];
check(validate('contact', $contact)['name'] === 'Test Customer', 'contact valid');
foreach ([['email','bad'],['name',str_repeat('a',121)],['name',"A\r\nBcc: test@example.com"],['message',[]]] as [$key,$value]) rejected(fn()=>validate('contact',array_replace($contact,[$key=>$value])),$key);
$quote=['name'=>'Test Customer','email'=>'quote@example.com','phone'=>'+265999277733','service'=>Vision\SERVICES[0],'quantity'=>'5','description'=>'Banner inquiry','date'=>'2027-01-15'];
check(validate('quote',$quote)['quantity']==='5','quote valid');
foreach ([['quantity','0'],['quantity','1.5'],['quantity','1000001'],['service','unknown'],['date','2027-02-30']] as [$key,$value]) rejected(fn()=>validate('quote',array_replace($quote,[$key=>$value])),$key);
$formatted=message('contact',validate('contact',$contact),'0123456789abcdef');
check(!str_contains($formatted['html'],'<script>'),'escaped HTML');
check(str_contains($formatted['text'],'<script>'),'plain text preserved');
$tmp=tempnam(sys_get_temp_dir(),'vp-test-');
try {
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII=');
    file_put_contents($tmp,$png);
    $upload=['error'=>UPLOAD_ERR_OK,'name'=>'design.png','tmp_name'=>$tmp];
    check(attachment($upload,false)['mime']==='image/png','valid PNG');
    rejected(fn()=>attachment($upload),'artwork'); // A local file is not an HTTP upload.
    foreach (['design.php','design.php.png','../design.png','design.svg','design.jpg'] as $name) rejected(fn()=>attachment(array_replace($upload,['name'=>$name]),false),'artwork');
    file_put_contents($tmp,$png.'<?php echo 1;'); rejected(fn()=>attachment($upload,false),'artwork');
    file_put_contents($tmp,str_repeat('x',Vision\MAX_FILE+1)); rejected(fn()=>attachment($upload,false),'artwork');
    clearstatcache(true,$tmp);
    copy(dirname(__DIR__,2).'/assets/optimized/press-540.jpg',$tmp);
    clearstatcache(true,$tmp);
    check(attachment(array_replace($upload,['name'=>'design.jpg']),false)['mime']==='image/jpeg','valid JPEG');
    file_put_contents($tmp,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    clearstatcache(true,$tmp);
    check(attachment(array_replace($upload,['name'=>'design.pdf']),false)['mime']==='application/pdf','PDF allowlist');
    file_put_contents($tmp,"%PDF-1.4\n/JavaScript (alert)\n%%EOF\n");
    clearstatcache(true,$tmp);
    rejected(fn()=>attachment(array_replace($upload,['name'=>'design.pdf']),false),'artwork');
    check(attachment(['error'=>UPLOAD_ERR_NO_FILE])===null,'optional file');
    rejected(fn()=>attachment(['error'=>UPLOAD_ERR_PARTIAL]),'artwork');
} finally { unlink($tmp); }
$dir=sys_get_temp_dir().'/vp-rate-'.bin2hex(random_bytes(8));
$cfg=['storage'=>$dir,'key'=>bin2hex(random_bytes(32))];
try {
    check(rateLimit($cfg,'test',2,60),'first rate slot'); check(rateLimit($cfg,'test',2,60),'second rate slot');
    check(!rateLimit($cfg,'test',2,60),'rate limit enforced');
    check(!str_contains(file_get_contents($dir.'/rate.json'),'test'),'no raw bucket in storage');
} finally { if(is_file($dir.'/rate.json')) unlink($dir.'/rate.json'); if(is_dir($dir)) rmdir($dir); }
echo "PASS: validation, header controls, escaped emails, attachments and rate limiting.\n";
