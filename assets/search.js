(function(root){
'use strict';
const norm=s=>String(s??'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim();
const stop=new Set('de del la el los las un una y o para con en por al a g gr kg ml lt litro litros lata tarro botella caja bolsa marca'.split(' '));
const tokens=s=>norm(s).split(' ').filter(t=>t.length>1&&!stop.has(t)&&!/^\d+(g|kg|ml|l)?$/.test(t));
// Conservative Spanish number variants. Every variant must exist in the index.
function forms(t){const r=new Set([t]);if(/[aeiou]$/.test(t))r.add(t+'s');else if(t.endsWith('z'))r.add(t.slice(0,-1)+'ces');else r.add(t+'es');if(t.length>4&&/[aeiou]s$/.test(t))r.add(t.slice(0,-1));if(t.length>4&&t.endsWith('es'))r.add(t.slice(0,-2));if(t.length>4&&t.endsWith('ces'))r.add(t.slice(0,-3)+'z');return [...r];}
function near(a,b){if(Math.abs(a.length-b.length)>1||a.length<4)return false;let i=0,j=0,e=0;while(i<a.length&&j<b.length){if(a[i]===b[j]){i++;j++;continue;}if(++e>1)return false;if(a.length>=b.length)i++;if(b.length>=a.length)j++;}return e+(i<a.length||j<b.length?1:0)<=1;}
class SearchEngine{
 constructor(catalog){this.catalog=catalog;this.classes=new Map(catalog.classes.map(r=>[String(r[0]),r]));this.families=new Map(catalog.families.map(r=>[String(r[0]),r]));this.segments=new Map(catalog.segments.map(r=>[String(r[0]),r]));this.byCode=new Map();this.index=new Map();this.items=catalog.products.map(r=>{const c=this.classes.get(String(r[1])),f=c&&this.families.get(String(c[1])),s=f&&this.segments.get(String(f[1]));const item={code:String(r[0]),name:r[2],classCode:String(r[1]),segment:s?String(s[0]):'',family:f?String(f[0]):'',group:s?String(s[1]):'',path:[s?.[2],f?.[2],c?.[2]].filter(Boolean),words:tokens(r[2]),normalized:norm(r[2])};this.byCode.set(item.code,item);for(const t of new Set(item.words)){if(!this.index.has(t))this.index.set(t,new Set());this.index.get(t).add(item.code);}return item;});this.vocabulary=[...this.index.keys()];}
 search(input,segment='',equivs=[],limit=30,filters={}){const q=norm(input),terms=tokens(input);this.total=0;const allowed=item=>(!segment||item.segment===segment)&&(!filters.group||item.group===filters.group)&&(!filters.family||item.family===filters.family)&&(!filters.classCode||item.classCode===filters.classCode);const hasFilter=segment||filters.group||filters.family||filters.classCode;
 if(!q){const list=hasFilter?this.items.filter(allowed):[];this.total=list.length;return list.slice(0,limit);}
 const exact=this.byCode.get(q);if(exact){this.total=allowed(exact)?1:0;return allowed(exact)?[{...exact,reason:'Código exacto',score:1000}]:[];}
 if(/^\d{2,7}$/.test(q)){const list=this.items.filter(i=>i.code.startsWith(q)&&allowed(i));this.total=list.length;return list.slice(0,limit).map(i=>({...i,reason:'Inicio de código',score:100}));}
 const candidates=new Map(),boost=new Map();
 const add=(code,score,reason)=>{const item=this.byCode.get(String(code));if(item&&allowed(item)){const old=candidates.get(item.code);candidates.set(item.code,{...item,score:(old?.score||0)+score,reason:reason||old?.reason||'Coincidencia de palabras'});}};
 for(const e of equivs)if(norm(e.reference)===q&&this.byCode.has(String(e.code)))boost.set(String(e.code),'Equivalencia confirmada');
 if(/\bleche\b/.test(q)&&!/\b(perro|perros|gato|gatos|soya|soja|suero)\b/.test(q)){
  if(/\bpolvo\b/.test(q))boost.set('50131704','Tipo de producto: leche en polvo');
  else if(/\b(evaporada|condensada|uht)\b/.test(q))boost.set('50131702','Sugerencia por tipo de leche: revisar clasificación de estante');
  else if(/\b(fresca|fresco)\b/.test(q))boost.set('50131701','Tipo de producto: leche fresca');
 }
 const termMatches=new Map();
 for(const t of terms){const variants=forms(t).filter(w=>this.index.has(w));let matches=variants.length?variants:this.vocabulary.filter(w=>near(t,w)).slice(0,12);const matchingIds=new Set(),termScores=new Map();for(const m of matches){const ids=this.index.get(m),weight=(variants.length?12:5)+Math.log(1+this.items.length/(ids.size+1));for(const id of ids){matchingIds.add(id);termScores.set(id,Math.max(termScores.get(id)||0,weight));}}termMatches.set(t,matchingIds);for(const [id,weight]of termScores)add(id,weight,variants.length?'Coincidencia de palabras (singular/plural)':'Posible variación al escribir');}
 for(const [code,reason]of boost)add(code,200,reason);
 let values=[...candidates.values()].filter(v=>filters.mode!=='all'||terms.every(t=>termMatches.get(t)?.has(v.code))); for(const v of values){if(v.normalized===q){v.score+=100;v.reason='Descripción exacta';}const hits=terms.filter(t=>termMatches.get(t)?.has(v.code)).length;if(terms.length&&hits===terms.length)v.score+=25;if(terms.includes('leche')&&v.segment==='50')v.score+=20;}
 // A brand alone must not be interpreted as a commercial product.
 if(q==='gloria')return [];
 this.total=values.length;return values.sort((a,b)=>b.score-a.score||a.code.localeCompare(b.code)).slice(0,limit);
 }
}
root.SunatSearch={SearchEngine,norm};if(typeof module!=='undefined')module.exports=root.SunatSearch;
})(typeof window==='undefined'?globalThis:window);
