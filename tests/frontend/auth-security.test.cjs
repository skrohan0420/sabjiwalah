const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
function element(){return{value:'',textContent:'',disabled:false,hidden:false,classList:{add(){},remove(){},toggle(){}},focus(){},select(){}};}
function setup(){
  const nodes=Object.fromEntries(['data-auth-phone','data-auth-dev-otp','data-auth-otp','data-auth-otp-panel','data-auth-otp-phone','data-auth-message','data-auth-redirect'].map(key=>['['+key+']',element()])),events={},calls=[];
  nodes['[data-auth-phone]'].value='9000000001';
  const form={classList:{toggle(){}},querySelector:selector=>nodes[selector]||null,querySelectorAll:()=>[],closest:()=>({querySelector:selector=>nodes[selector]||null})};
  const button=element();button.closest=()=>form;
  const context={document:{querySelector:selector=>nodes[selector]||null,addEventListener:(name,handler)=>events[name]=handler},window:{Sabjiwalah:{url:path=>path},localStorage:{getItem:()=>null,setItem(){}}},fetch:(path,options)=>new Promise((resolve,reject)=>calls.push({path,options,resolve,reject})),Array,JSON,String,Error};
  vm.runInNewContext(fs.readFileSync('public/assets/js/auth.js','utf8'),context);
  return{nodes,form,button,events,calls};
}
function send(s){return{target:{closest:selector=>selector==='[data-send-auth-otp]'?s.button:null}};}
function respond(call,data,status=200,message=''){call.resolve({ok:status<400,json:async()=>({success:status<400,data,message})});}
test('OTP issuance blocks duplicate clicks, phone changes and verification until the request finishes',async()=>{
  const s=setup();s.events.click(send(s));assert.equal(s.button.disabled,true);assert.equal(s.nodes['[data-auth-phone]'].disabled,true);
  s.events.click(send(s));s.events.click({target:{closest:selector=>selector==='[data-change-auth-phone]'?s.button:null}});s.events.submit({preventDefault(){},target:{closest:()=>s.form}});assert.equal(s.calls.length,1);
  respond(s.calls[0],{header_name:'X-CSRF',token_value:'fresh'});await flush();assert.equal(s.calls[1].path,'/api/v1/auth/otp/start');assert.deepEqual(JSON.parse(s.calls[1].options.body),{phone:'9000000001'});
  respond(s.calls[1],{dev_otp:'123456',user_exists:true,user_name:'Sample'});await flush();assert.equal(s.button.disabled,false);assert.equal(s.nodes['[data-auth-phone]'].disabled,false);assert.match(s.nodes['[data-auth-dev-otp]'].textContent,/123456/);
});
test('rate-limit and unavailable responses explain the error and never retry issuance automatically',async()=>{
  for(const [status,message]of[[429,'Too many OTP requests. Try again in 60 seconds.'],[503,'Phone verification is unavailable. Please contact the shop.']]){
    const s=setup();s.events.click(send(s));respond(s.calls[0],{header_name:'X-CSRF',token_value:'fresh'});await flush();respond(s.calls[1],null,status,message);await flush();
    assert.equal(s.calls.length,2);assert.equal(s.button.disabled,false);assert.equal(s.nodes['[data-auth-phone]'].disabled,false);assert.equal(s.nodes['[data-auth-message]'].textContent,message);assert.equal(s.nodes['[data-auth-dev-otp]'].hidden,true);
  }
});
