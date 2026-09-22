const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const sandbox = {window:{}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/catalog.js'),'utf8'),sandbox);
const {SearchEngine} = require('../assets/search.js');
const e = new SearchEngine(sandbox.window.CATALOG);
assert.equal(e.items.length,49022);
for(const [query,code] of [['50131704','50131704'],['Leche en polvo Gloria','50131704'],['Leche evaporada Gloria lata 400 g','50131702'],['Leche fresca','50131701'],['LECHE EN PÓLVO','50131704']]) assert.equal(e.search(query)[0].code,code,query);
assert.equal(e.search('Gloria').length,0);
assert.equal(e.search('xyz987unknown').length,0);
assert.equal(e.search('50131704','10').length,0);
assert(e.search('leche').length>1);
assert.equal(e.search('Referencia local','',[{reference:'referencia local',code:'50131704'}])[0].code,'50131704');
assert.equal(e.search('Referencia local','',[{reference:'referencia local',code:'99999999'}]).some(r=>r.code==='99999999'),false);
console.log('Pruebas del catálogo original y buscador: correctas.');
// V2: number variants and optional filters must not lose results before ranking.
for(const [a,b]of [['aceite','aceites'],['motor','motores'],['lapiz','lapices']]){
 const ids=q=>e.search(q,'',[],100000).map(r=>r.code).sort();
 assert.deepEqual(ids(a),ids(b),a+' / '+b);
}
const oil=e.search('aceite','',[],100000);assert.equal(oil.length,98);
const food=e.search('aceites','50',[],100);assert(food.length>0);assert(food.every(r=>r.segment==='50'));
const target=oil.find(r=>r.segment!=='50');
const filtered=e.search('aceites','',[],1,{family:target.family,classCode:target.classCode,group:target.group});assert.equal(filtered.length,1);assert.equal(filtered[0].classCode,target.classCode);assert(e.total>=filtered.length);
assert.equal(e.search(target.code,'50',[],30).length,0);
assert(e.search('','',[],30,{classCode:target.classCode}).every(r=>r.classCode===target.classCode));
assert(e.search('5013','',[],30).every(r=>r.code.startsWith('5013')));
assert.equal(e.search('aceite xyz987unknown','',[],30,{mode:'all'}).length,0);
assert.equal(e.total,0);
console.log('V2: singular/plural, categorías, límites, prefijos y coincidencia estricta: correctos.');
