// Run only against the loopback PHP mock server documented in backend/README.md.
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
const origin='http://127.0.0.1:8080';
let response=await fetch(origin+'/api/session.php');
const session=await response.json();
assert.equal(response.status,200,'Install Composer dependencies and start PHP mock mode first.');
assert.equal(session.mode,'mock','Refusing to run against anything except verified mock mode.');
const cookie=response.headers.get('set-cookie').split(';')[0];
await new Promise(resolve=>setTimeout(resolve,2200));
const common={name:'Local Test',email:'contact@example.com',phone:'',subject:'Local test',message:'No real email',website:'',csrf:session.csrf};
async function post(kind, fields, file, requestOrigin=origin){
 const body=new FormData();
 for(const [name,value] of Object.entries({request_id:randomUUID(),...fields})) body.set(name,value);
 if(file) body.set('artwork',new Blob([file.bytes],{type:file.mime}),file.name);
 const res=await fetch(origin+`/api/${kind}.php`,{method:'POST',headers:{Cookie:cookie,Origin:requestOrigin},body});
 return {status:res.status,body:await res.json()};
}
assert.equal((await post('contact',common,null,'https://untrusted.example')).status,403);
assert.equal((await post('contact',{...common,csrf:'bad'})).status,403);
assert.equal((await post('contact',{...common,email:'bad'})).status,422);
const id=randomUUID();
const success=await post('contact',{...common,request_id:id});
assert.equal(success.status,200); assert.equal(success.body.status,'mocked'); assert.equal(success.body.acknowledgment,'mocked');
const duplicate=await post('contact',{...common,request_id:id}); assert.equal(duplicate.body.reference,success.body.reference);
assert.equal((await post('contact',{...common,website:'spam'})).status,422);
assert.equal((await post('contact',common)).status,429);
const quote={name:'Local Test',email:'quote@example.com',phone:'+265999277733',service:'Graphic Design & Branding',quantity:'10',description:'Local attachment test',date:'2027-01-15',website:'',csrf:session.csrf};
const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII=','base64');
assert.equal((await post('quote',quote,{name:'design.php',mime:'image/png',bytes:png})).status,422);
assert.equal((await post('quote',quote,{name:'design.jpg',mime:'image/jpeg',bytes:png})).status,422);
assert.equal((await post('quote',{...quote,quantity:'0'})).status,422);
const good=await post('quote',quote,{name:'design.png',mime:'image/png',bytes:png});
assert.equal(good.status,200); assert.equal(good.body.status,'mocked');
assert.equal((await post('quote',quote,{name:'large.png',mime:'image/png',bytes:Buffer.alloc(10485761)})).status,422);
assert.equal((await fetch(origin+'/api/contact.php')).status,405);
console.log('PASS: mock contact/quote success, acknowledgment preparation, duplicate suppression, CSRF, origin, honeypot, invalid inputs, attachment MIME/extension/size and rate limits. No real email sent.');
