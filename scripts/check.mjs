import { readFile, access } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { company } from '../src/config.mjs';
const pages=['index.html','services.html','equipment.html','about.html','contact.html','quote.html'];
for(const page of pages){
 const html=await readFile('dist/'+page,'utf8');
 assert(html.includes('<main id="main">'),page+' main landmark');
 assert.equal((html.match(/<h1[ >]/g)||[]).length,1,page+' heading');
 for(const [,url] of html.matchAll(/(?:href|src)="([^"]+)"/g)){
  if(/^(https?:|mailto:|tel:|#)/.test(url)) continue;
  const [file,hash]=url.split('#'); const target=decodeURIComponent(file.split('?')[0]);
  await access('dist/'+target);
  if(hash) assert((await readFile('dist/'+target,'utf8')).includes(`id="${hash}"`),`${page}: missing anchor ${url}`);
 }
 if(page==='contact.html'||page==='quote.html'){
  assert(html.includes('submissions are not active'));
  assert(!html.includes('action='));
  assert(html.includes(`Future inquiries will be delivered to ${company.formRecipient}.`));
  const ids=new Set([...html.matchAll(/id="([^"]+)"/g)].map(m=>m[1]));
  for(const [,id] of html.matchAll(/<label for="([^"]+)"/g)) assert(ids.has(id));
 }
}
const contact=await readFile('dist/contact.html','utf8');
assert.equal(company.formRecipient,'sales@visionprintersmw.com');
for(const number of [company.phone,...company.additionalPhones]) assert(contact.includes(`href="tel:${number}"`),`Missing telephone ${number}`);
for(const email of [company.email,company.secondaryEmail]) assert(contact.includes(`href="mailto:${email}"`),`Missing email ${email}`);
assert(contact.includes('href="https://wa.me/265999277733"'));
assert(contact.includes('href="https://www.google.com/maps?q=-15.785153525959059,35.00532969325329"'));
for(const page of pages){
 const html=await readFile('dist/'+page,'utf8');
 assert(html.includes(`href="tel:${company.phone}"`));
 assert(html.includes(`href="https://wa.me/${company.whatsapp}"`));
}
console.log('PASS: configured telephone, email, WhatsApp and map destinations; sole future form recipient.');
console.log('PASS: six pages, local links/assets, anchor destinations, headings, form disclosures and label targets.');
