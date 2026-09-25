/* Regenerate vector exports and package native captures. Requires sharp through NODE_PATH. */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const sharp = require('sharp');
const root = path.resolve(__dirname, '../../..');
const out = path.join(root, 'output/store-assets');
const entries = [];
const svg = (w,h,body) => `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}">${body}</svg>`;
const mark = fs.readFileSync(path.join(root,'output/brand/source/symbol-navy.svg'),'utf8').replace(/^.*?<g/,'<g').replace('</svg>','');
const feature = svg(1024,500,`<defs><linearGradient id="bg" x2="1" y2="1"><stop stop-color="#C4FFE2"/><stop offset="1" stop-color="#75EAC0"/></linearGradient></defs>
<rect width="1024" height="500" fill="url(#bg)"/>
<circle cx="930" cy="450" r="285" fill="#A7F8DA"/><circle cx="40" cy="-110" r="230" fill="#D8FFEB"/>
<g transform="translate(82 68) scale(.37)">${mark}</g><text x="134" y="100" fill="#0A1128" font-family="Arial,sans-serif" font-size="33" font-weight="600" letter-spacing="-1">ovezi</text>
<text x="84" y="206" fill="#0A1128" font-family="Arial,sans-serif" font-size="65" font-weight="600" letter-spacing="-2">Good times.</text>
<text x="84" y="282" fill="#006A50" font-family="Arial,sans-serif" font-size="65" font-weight="600" letter-spacing="-2">Fair shares.</text>
<text x="86" y="345" fill="#254C43" font-family="Arial,sans-serif" font-size="24">Shared expenses, clearly balanced.</text>
<g transform="translate(669 106) rotate(8 110 130)"><rect y="9" width="238" height="283" rx="30" fill="#006A50" opacity=".09"/><rect width="238" height="283" rx="30" fill="#F7FAF7"/>
<circle cx="119" cy="74" r="39" fill="#DDF5EC"/><path d="M98 75l14 14 28-30" fill="none" stroke="#006A50" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
<text x="119" y="154" text-anchor="middle" fill="#0A1128" font-family="Arial,sans-serif" font-size="25" font-weight="600">Better together</text>
<rect x="38" y="182" width="162" height="7" rx="3.5" fill="#D8E2DD"/><rect x="63" y="202" width="112" height="7" rx="3.5" fill="#D8E2DD"/>
<circle cx="83" cy="253" r="14" fill="#BDD3FC"/><circle cx="119" cy="253" r="14" fill="#BDE7D6"/><circle cx="155" cy="253" r="14" fill="#E4CEF4"/></g>`);
function write(relative, data) {const file=path.join(out,relative); fs.mkdirSync(path.dirname(file),{recursive:true});fs.writeFileSync(file,data);}
async function describe(relative,purpose,use,source) {const file=path.join(out,relative),b=fs.readFileSync(file),m=await sharp(b).metadata();entries.push({file:relative,purpose,width:m.width,height:m.height,format:m.format,channels:m.channels,transparency:m.hasAlpha?'RGBA; alpha fully opaque unless designated transparent layer':'RGB; no alpha',bytes:b.length,sha256:crypto.createHash('sha256').update(b).digest('hex'),usedBy:use,source});}
(async()=>{
const names=['icon-light.png','icon-dark.png','icon-tinted.png','android-icon-foreground.png','android-icon-monochrome.png','splash-icon.png','splash-icon-dark.png','logo-mark-navy.png','logo-mark-mint.png','favicon.png'];
write('expo/icon.png',fs.readFileSync(path.join(root,'app/assets/images/icon-dark.png')));await describe('expo/icon.png','Optional generic export of the official dark icon',['External packaging only'],'source/icon-dark.svg');
for(const name of names){write(`expo/${name}`,fs.readFileSync(path.join(root,'app/assets/images',name)));await describe(`expo/${name}`,'Expo runtime image',['app/app.json','app/src/components/brand-mark.tsx'],`app/assets/images/${name}`);}
const background=svg(1024,1024,'<rect width="1024" height="1024" fill="#0A1128"/>');
const bg=await sharp(Buffer.from(background)).removeAlpha().png().toBuffer();write('expo/android-icon-background.png',bg);await describe('expo/android-icon-background.png','Optional opaque adaptive background',['app/app.json uses equivalent backgroundColor'],'source/android-background.svg');
const notification=svg(96,96,`<g transform="translate(10 10) scale(.76)">${mark.replaceAll('#0A1128','#FFFFFF')}</g>`);
const notificationPng=await sharp(Buffer.from(notification)).png().toBuffer();write('expo/notification-icon.png',notificationPng);fs.writeFileSync(path.join(root,'app/assets/images/notification-icon.png'),notificationPng);write('source/notification-icon.svg',notification);await describe('expo/notification-icon.png','White silhouette Android notification icon',['app/app.json → expo-notifications'],'source/notification-icon.svg');
for(const e of entries){if(/foreground|monochrome|splash|logo-mark|notification/.test(e.file)) e.transparency='Transparent outside artwork';}
for(const name of fs.readdirSync(path.join(root,'output/brand/source')).filter(x=>x.endsWith('.svg')))write('source/'+name,fs.readFileSync(path.join(root,'output/brand/source',name)));
for(const name of ['light','dark','tinted']){write(`app-store/icon-${name}-1024.png`,fs.readFileSync(path.join(root,`app/assets/images/icon-${name}.png`)));await describe(`app-store/icon-${name}-1024.png`,name==='light'?'Optional light artwork; not configured as the primary store icon':name==='dark'?'Official iOS default and dark appearance icon':'iOS tinted appearance icon',[name==='light'?'Optional artwork; retained for future alternate-icon support':'Expo iOS icon; App Store icon is supplied by the app binary'],`source/icon-${name}.svg`);}
write('app-store/icon-1024.png',fs.readFileSync(path.join(root,'app/assets/images/icon-dark.png')));await describe('app-store/icon-1024.png','Official App Store icon: approved dark artwork',['app/app.json → ios.icon.light and ios.icon.dark'],'source/icon-dark.svg');
const play=await sharp(path.join(root,'output/brand/source/icon-dark.svg')).resize(512,512).ensureAlpha(1).png().toBuffer();write('google-play/icon-512.png',play);fs.writeFileSync(path.join(root,'output/brand/google-play-icon.png'),play);await describe('google-play/icon-512.png','Google Play 32-bit listing icon',['Play Console → Main store listing → App icon'],'source/icon-dark.svg');
write('source/feature-graphic.svg',feature);const featurePng=await sharp(Buffer.from(feature)).removeAlpha().png().toBuffer();write('google-play/feature-graphic-1024x500.png',featurePng);fs.writeFileSync(path.join(root,'output/brand/google-play-feature.svg'),feature);fs.writeFileSync(path.join(root,'output/brand/google-play-feature.png'),featurePng);await describe('google-play/feature-graphic-1024x500.png','Feature graphic; alt: Ovezi: Good times. Fair shares. Shared expenses, clearly balanced.',['Play Console → Feature graphic'],'source/feature-graphic.svg');
for(const name of fs.readdirSync(path.join(root,'output/brand')).filter(x=>/^(campaign-|social-preview)/.test(x))){write('campaign/'+name,fs.readFileSync(path.join(root,'output/brand',name)));if(name.endsWith('.png'))await describe('campaign/'+name,'Campaign artwork',['External marketing placement'],`campaign/${name.replace('.png','.svg')}`);}
for(const [folder,platform] of [['app-store/screenshots/iphone-6.9','ios'],['google-play/screenshots/phone','android']]){for(const name of fs.readdirSync(path.join(out,folder)).filter(x=>x.endsWith('.png'))){const rel=folder+'/'+name;const file=path.join(out,rel);const b=await sharp(file).removeAlpha().png().toBuffer();fs.writeFileSync(file,b);await describe(rel,'Native '+platform+' screenshot with fictional sample data',[platform==='ios'?'App Store Connect → iPhone 6.9-inch':'Play Console → Phone screenshots'],'Native simulator/emulator capture; see capture-provenance.json');}}
for (const folder of ['source','campaign']) { for (const name of fs.readdirSync(path.join(out,folder)).filter(x=>x.endsWith('.svg'))) await describe(folder+'/'+name,'Editable vector source',['Source artwork; edit before regenerating raster exports'],folder+'/'+name); }
for (const name of ['favicon.ico','favicon.svg','favicon-32.png','apple-touch-icon.png','icon-192.png','icon-512.png','site.webmanifest','brand/wordmark.png','brand/social-preview.png']) {
 const file=path.join(root,'api/public',name), relative='laravel/'+name, data=fs.readFileSync(file);
 write(relative,data);
 if (/\.(png|svg)$/.test(name)) await describe(relative,'Laravel runtime branding',['api/public/'+name,'Public pages, transactional mail or web manifest'],'Canonical masters in output/brand/source');
 else entries.push({file:relative,purpose:name.endsWith('.ico')?'Multi-size browser favicon':'Progressive web app icon manifest',width:name.endsWith('.ico')?[16,32,48]:null,height:name.endsWith('.ico')?[16,32,48]:null,format:path.extname(name).slice(1),transparency:name.endsWith('.ico')?'Supports alpha; contains multiple icon sizes':'Not applicable',bytes:data.length,sha256:crypto.createHash('sha256').update(data).digest('hex'),usedBy:['api/public/'+name],source:'output/brand/source/generate.cjs'});
}
write('asset-manifest.json',JSON.stringify({generated:new Date().toISOString(),files:entries},null,2));
for(const relative of ['output/brand/source/asset-manifest.json','output/brand/asset-manifest.json']) { const file=path.join(root,relative); const old=JSON.parse(fs.readFileSync(file)); const play=old.find(x=>x.file==='output/brand/google-play-icon.png'); if(play) play.transparency='32-bit RGBA PNG; fully opaque artwork'; if(!old.some(x=>x.file==='app/assets/images/notification-icon.png')) old.push({file:'app/assets/images/notification-icon.png',purpose:'Android notification silhouette',width:96,height:96,format:'png',transparency:'Transparent outside white artwork',usedBy:['app/app.json → expo-notifications']}); fs.writeFileSync(file,JSON.stringify(old,null,2)); }
console.log(`Packaged ${entries.length} assets, including editable SVG sources.`);
})();
