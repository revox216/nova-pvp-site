const {Client,GatewayIntentBits:G,ActionRowBuilder:Row,StringSelectMenuBuilder,ButtonBuilder,ButtonStyle,EmbedBuilder,ModalBuilder,TextInputBuilder,TextInputStyle,ChannelType,PermissionFlagsBits:P,ActivityType,Events,Partials,AuditLogEvent,AttachmentBuilder}=require('discord.js');
const {DatabaseSync}=require('node:sqlite');const path=require('path');const cfg=require('./config.json');
const db=new DatabaseSync(path.resolve(__dirname,cfg.dbPath));
db.exec(`PRAGMA busy_timeout=8000;PRAGMA journal_mode=WAL;
CREATE TABLE IF NOT EXISTS tickets(id INTEGER PRIMARY KEY AUTOINCREMENT,channel_id TEXT,user_id TEXT,username TEXT,type TEXT,status TEXT,created_at TEXT,closed_at TEXT,closed_by TEXT,transcript TEXT);
CREATE TABLE IF NOT EXISTS cheaters(id INTEGER PRIMARY KEY AUTOINCREMENT,discord_id TEXT UNIQUE,discord_name TEXT,mta_name TEXT,mta_id TEXT,proof TEXT,added_by TEXT,added_by_name TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS bot_state(k TEXT PRIMARY KEY,v TEXT);
CREATE TABLE IF NOT EXISTS logs(id INTEGER PRIMARY KEY AUTOINCREMENT,actor_id TEXT,actor_name TEXT,action TEXT,details TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS suggestions(id INTEGER PRIMARY KEY AUTOINCREMENT,message_id TEXT,channel_id TEXT,author_id TEXT,author_name TEXT,content TEXT,created_at TEXT);
CREATE TABLE IF NOT EXISTS suggestion_votes(id INTEGER PRIMARY KEY AUTOINCREMENT,suggestion_id INTEGER,user_id TEXT,username TEXT,vote INTEGER,created_at TEXT,UNIQUE(suggestion_id,user_id));
CREATE TABLE IF NOT EXISTS activity(discord_id TEXT,day TEXT,messages INTEGER DEFAULT 0,voice INTEGER DEFAULT 0,PRIMARY KEY(discord_id,day));
CREATE TABLE IF NOT EXISTS staff(id INTEGER PRIMARY KEY AUTOINCREMENT,discord_id TEXT,role_id INTEGER,username TEXT,avatar TEXT);
CREATE TABLE IF NOT EXISTS members(discord_id TEXT PRIMARY KEY,username TEXT,avatar TEXT);
CREATE TABLE IF NOT EXISTS invite_joins(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id TEXT,inviter_id TEXT,code TEXT,joined_at TEXT,left_at TEXT);
CREATE INDEX IF NOT EXISTS ij_inviter ON invite_joins(inviter_id);
CREATE TABLE IF NOT EXISTS invite_baseline(inviter_id TEXT PRIMARY KEY,uses INTEGER);
CREATE TABLE IF NOT EXISTS discord_logs(event TEXT PRIMARY KEY,enabled INTEGER DEFAULT 0,channel_id TEXT,color TEXT DEFAULT '#00ff66');`);
try{db.exec('ALTER TABLE tickets ADD COLUMN claimed_by TEXT')}catch{}
const log=(id,name,a,d)=>db.prepare("INSERT INTO logs(actor_id,actor_name,action,details,created_at) VALUES(?,?,?,?,datetime('now'))").run(id,name,a,d);
const getS=k=>db.prepare('SELECT v FROM bot_state WHERE k=?').get(k)?.v;const setS=(k,v)=>db.prepare('REPLACE INTO bot_state(k,v) VALUES(?,?)').run(k,v);
const TYPES={support:'Support Ticket',ban_appeal:'Ban Appeal',bug_report:'Bug Report',partnership:'Partnership'};
const EPH=64,GREEN=0x00ff66,LABEL={...TYPES,shop:'Shop Order'};

const ALLOWED_CLOSE_CATEGORY = "1564281201785708724";
const TARGET_CLOSED_CATEGORY = "1564281219787661372";
const TRANSCRIPT_ROLE_ID = "1569828614260654182";

const isStaff=i=>cfg.ownerIds.includes(i?.user?.id || i?.author?.id)||!!i?.member?.roles?.cache?.some(r=>cfg.staffRoleIds.includes(r.id));
const SUG=cfg.suggestionChannelId||'1565103776455462965',SERVER=cfg.serverName||'NOVA PVP';
const sgMsg=s=>{const c=q=>db.prepare('SELECT COUNT(*) c FROM suggestion_votes WHERE suggestion_id=? AND vote=?').get(s.id,q).c,up=c(1),dn=c(-1),t=up+dn,pct=t?Math.round(up/t*100):0,f=Math.round(pct/10);
 return {embeds:[new EmbedBuilder().setColor(GREEN).setAuthor({name:`${SERVER} • Community Suggestion`}).setTitle(`🔔 Suggestion #${s.id}`).setDescription(`**Submitted by** <@${s.author_id}>\n\n> ${s.content.replace(/\n/g,'\n> ')}`)
  .addFields({name:'Community approval',value:`${'▰'.repeat(f)}${'▱'.repeat(10-f)} ${pct}% approval • 👍 ${up} • 👎 ${dn}`}).setFooter({text:`${SERVER} Suggestion System`}).setTimestamp(new Date(s.created_at.replace(' ','T')+'Z'))],
  components:[new Row().addComponents(new ButtonBuilder().setCustomId('sg_up').setLabel(`Upvote • ${up}`).setStyle(ButtonStyle.Success).setEmoji('👍'),new ButtonBuilder().setCustomId('sg_down').setLabel(`Downvote • ${dn}`).setStyle(ButtonStyle.Danger).setEmoji('👎'))]};};
const NAMES=new Map(),touch=u=>{if(!u||u.bot)return;const n=u.globalName||u.username;if(NAMES.get(u.id)===n)return;NAMES.set(u.id,n);db.prepare('INSERT INTO members(discord_id,username,avatar) VALUES(?,?,?) ON CONFLICT(discord_id) DO UPDATE SET username=excluded.username,avatar=excluded.avatar').run(u.id,n,u.displayAvatarURL({size:64}))};
const VJ=new Map(),addAct=(id,col,n)=>db.prepare(`INSERT INTO activity(discord_id,day,${col}) VALUES(?,date('now'),?) ON CONFLICT(discord_id,day) DO UPDATE SET ${col}=${col}+?`).run(id,n,n);
const ticketButtons=()=>new Row().addComponents(new ButtonBuilder().setCustomId('ticket_close').setLabel('Close').setStyle(ButtonStyle.Danger).setEmoji('🔒'),new ButtonBuilder().setCustomId('ticket_claim').setLabel('Claim').setStyle(ButtonStyle.Primary).setEmoji('🙋'),new ButtonBuilder().setCustomId('ticket_dm').setLabel('Send DM').setStyle(ButtonStyle.Secondary).setEmoji('✉️'));
const client=new Client({intents:[G.Guilds,G.GuildMembers,G.GuildMessages,G.MessageContent,G.GuildModeration,G.GuildVoiceStates,G.GuildInvites],partials:[Partials.Message,Partials.Channel,Partials.GuildMember,Partials.User]});

const ticketPanel=()=>({embeds:[new EmbedBuilder().setColor(GREEN).setTitle('🎫 Support Center').setDescription('Choose the type of ticket from the list below.')],
 components:[new Row().addComponents(new StringSelectMenuBuilder().setCustomId('ticket_type').setPlaceholder('Select a ticket type…').addOptions(Object.entries(TYPES).map(([value,label])=>({label,value}))))]});
const cheaterPanel=()=>({embeds:[new EmbedBuilder().setColor(0xff3333).setTitle('🚨 Cheaters List').setDescription('Staff: click the button to add a cheater.\nThe full list is available on the website.')],
 components:[new Row().addComponents(new ButtonBuilder().setCustomId('cheater_add').setLabel('Add Cheater').setStyle(ButtonStyle.Danger))]});
async function ensurePanel(key,channelId,make){if(!channelId)return;const ch=await client.channels.fetch(channelId).catch(()=>null);if(!ch)return console.log('Channel not found for',key);
 const id=getS(key);if(id&&await ch.messages.fetch(id).catch(()=>null))return;setS(key,(await ch.send(make())).id);}
async function updatePresence(){const g=await client.guilds.fetch({guild:cfg.guildId,withCounts:true,force:true}).catch(()=>null);if(!g)return;
 client.user.setPresence({status:'online',activities:[{name:`${g.approximatePresenceCount??0} online`,type:ActivityType.Watching}]});}
const canManage=id=>cfg.ownerIds.includes(id)||(db.prepare('SELECT perms FROM access WHERE discord_id=?').get(id)?.perms||'').split(',').includes('cheaters_manage');

client.once(Events.ClientReady,async()=>{
 console.log('Bot online as',client.user.tag);
 await ensurePanel('ticket_panel',cfg.ticketPanelChannelId,ticketPanel);
 await ensurePanel('cheater_panel',cfg.cheatersChannelId,cheaterPanel);
 updatePresence();
 setInterval(updatePresence,60000);
 client.guilds.cache.get(cfg.guildId)?.voiceStates.cache.forEach(v=>{if(v.channelId&&!v.member?.user.bot){VJ.set(v.id,Date.now());touch(v.member?.user)}});

 const guild = client.guilds.cache.get(cfg.guildId);
 if(guild){
  await guild.commands.create({name: 'setup-ticket', description: 'Setup the ticket panel in a dedicated room (Server Owner only)', defaultMemberPermissions: P.Administrator}).catch(console.error);
  await guild.commands.create({name: 'dmall', description: 'Send a direct message to all members in the server (Staff only)', options: [{name: 'message', description: 'The message to send to everyone', type: 3, required: true}], defaultMemberPermissions: P.ModerateMembers}).catch(console.error);
  await guild.commands.create({name: 'dmid', description: 'Send a direct message to a specific member by their Discord ID (Staff only)', options: [{name: 'userid', description: 'The Discord ID of the member', type: 3, required: true},{name: 'message', description: 'The message to send', type: 3, required: true}], defaultMemberPermissions: P.ModerateMembers}).catch(console.error);
 }
});

const CD=new Map();setInterval(()=>{const n=Date.now();for(const[k,v]of CD)if(v<n)CD.delete(k)},60000);
client.on(Events.InteractionCreate,async i=>{try{
 if(i.isButton()||i.isStringSelectMenu()){const k=i.user.id+':'+i.customId,n=Date.now();if((CD.get(k)||0)>n)return i.reply({content:`⏳ Please wait ${Math.ceil((CD.get(k)-n)/1000)}s before using this again.`,flags:EPH});CD.set(k,n+(i.customId.startsWith('sg_')?10000:5000));}

 if(i.isChatInputCommand()&&i.commandName==='setup-ticket'){
  if(i.guild.ownerId!==i.user.id&&!i.member.permissions.has(P.Administrator)){
   return i.reply({content:'❌ Only the Server Owner or Administrators can use this command!',flags:EPH});
  }
  await i.deferReply({flags:EPH});
  const ch=await i.guild.channels.create({name:`ticket-panel`,type:ChannelType.GuildText,permissionOverwrites:[{id:i.guild.id,type:0,allow:[P.ViewChannel,P.ReadMessageHistory],deny:[P.SendMessages]}]});
  await ch.send(ticketPanel());
  return i.editReply(`✅ Ticket panel successfully setup in: ${ch}`);
 }

 if(i.isChatInputCommand()&&i.commandName==='dmall'){
  if(!isStaff(i))return i.reply({content:'❌ Only staff members can use this command!',flags:EPH});
  const text=i.options.getString('message');
  await i.deferReply({flags:EPH});
  let success=0, failed=0;
  const members=await i.guild.members.fetch();
  for(const member of members.values()){
   if(member.user.bot)continue;
   try{await member.send({embeds:[new EmbedBuilder().setColor(GREEN).setTitle(`📢 Notification from ${SERVER}`).setDescription(text)]});success++;}catch{failed++;}
  }
  return i.editReply(`✅ Message sent to **${success}** members. (Failed: ${failed}).`);
 }

 if(i.isChatInputCommand()&&i.commandName==='dmid'){
  if(!isStaff(i))return i.reply({content:'❌ Only staff members can use this command!',flags:EPH});
  const userId=i.options.getString('userid').trim();
  const text=i.options.getString('message');
  await i.deferReply({flags:EPH});
  try{
   const user=await client.users.fetch(userId);
   if(!user)return i.editReply('❌ User not found with this ID!');
   await user.send({embeds:[new EmbedBuilder().setColor(GREEN).setTitle(`📩 Message from ${SERVER} Staff`).setDescription(text)]});
   return i.editReply(`✅ Message successfully sent to <@${userId}>!`);
  }catch(e){return i.editReply(`❌ Could not send DM to this user.`);}
 }

 if(i.isStringSelectMenu()&&i.customId==='ticket_type'){
  const type=i.values[0];i.message.edit(ticketPanel()).catch(()=>{});
  const open=db.prepare("SELECT channel_id FROM tickets WHERE user_id=? AND status='open' AND type!='shop'").get(i.user.id);
  if(open)return i.reply({content:`You already have an open ticket: <#${open.channel_id}>`,flags:EPH});
  await i.deferReply({flags:EPH});
  const ch=await i.guild.channels.create({name:`${type.replace('_','-')}-${i.user.username}`.slice(0,90),type:ChannelType.GuildText,parent:cfg.categories[type]||null,
   permissionOverwrites:[{id:i.guild.id,type:0,deny:[P.ViewChannel]},{id:i.user.id,type:1,allow:[P.ViewChannel,P.SendMessages,P.ReadMessageHistory,P.AttachFiles]},
   ...cfg.staffRoleIds.filter(Boolean).map(r=>({id:String(r),type:0,allow:[P.ViewChannel,P.SendMessages,P.ReadMessageHistory,P.AttachFiles]}))]});
  db.prepare("INSERT INTO tickets(channel_id,user_id,username,type,status,created_at) VALUES(?,?,?,?,'open',datetime('now'))").run(ch.id,i.user.id,i.user.username,type);
  log(i.user.id,i.user.username,'ticket_open',`${TYPES[type]} (#${ch.name})`);
  await ch.send({content:`<@${i.user.id}>`,embeds:[new EmbedBuilder().setColor(GREEN).setTitle(TYPES[type]).setDescription('Describe your issue and a staff member will help you soon.')],components:[ticketButtons()]});
  return i.editReply(`Your ticket was created: ${ch}`);
 }

 // Transcript button handler
 if(i.isButton()&&i.customId.startsWith('get_transcript_')){
  const hasRole = i.member?.roles?.cache?.has(TRANSCRIPT_ROLE_ID);
  if(!hasRole) return i.reply({content:'❌ You do not have the required role to get the transcript!', flags: EPH});
  
  await i.deferReply({flags: EPH});
  
  const ticketId = i.customId.replace('get_transcript_', '');
  const t = db.prepare("SELECT * FROM tickets WHERE id=?").get(ticketId);
  if(!t||!t.transcript) return i.editReply('❌ Transcript data not found!');
  
  try {
   const data = JSON.parse(t.transcript);
   let txtContent = `=== TRANSCRIPT TICKET #${t.id} (${t.type}) ===\nUser: ${t.username} (${t.user_id})\nClosed By: ${t.closed_by}\n\n`;
   data.forEach(m => {
    txtContent += `[${m.t}] ${m.a} (${m.id}): ${m.c}\n`;
    if(m.f && m.f.length) txtContent += `   Files: ${m.f.join(', ')}\n`;
   });
   const buffer = Buffer.from(txtContent, 'utf-8');
   const attachment = new AttachmentBuilder(buffer, { name: `transcript-ticket-${t.id}.txt` });
   await i.user.send({content: `📄 **Transcript for Ticket #${t.id}** (${t.username}):`, files: [attachment]});
   return i.editReply('✅ The transcript has been sent to your DMs successfully!');
  } catch(e) {
   console.error(e);
   return i.editReply('❌ Could not send the transcript to your DMs (Check if your DMs are open).');
  }
 }

 if(i.isButton()&&i.customId==='ticket_close'){
  let t = db.prepare("SELECT * FROM tickets WHERE channel_id=? AND status='open'").get(i.channelId);
  if(!t){
   t = db.prepare("SELECT * FROM tickets WHERE channel_id=?").get(i.channelId);
   if(!t){
    db.prepare("INSERT INTO tickets(channel_id,user_id,username,type,status,created_at) VALUES(?,?,?,?,?,datetime('now'))").run(i.channelId,i.user.id,i.user.username,'support','open');
    t = db.prepare("SELECT * FROM tickets WHERE channel_id=?”").get(i.channelId);
   }else{
    db.prepare("UPDATE tickets SET status='open' WHERE channel_id=?").run(i.channelId);
   }
  }
  const ok=i.user.id===t.user_id||cfg.ownerIds.includes(i.user.id)||i.member.roles.cache.some(r=>cfg.staffRoleIds.includes(r.id));
  if(!ok)return i.reply({content:'You cannot close this ticket.',flags:EPH});
  
  await i.deferReply();
  
  let all=[],before;
  for(let n=0;n<10;n++){const b=await i.channel.messages.fetch({limit:100,before});if(!b.size)break;all.push(...b.values());before=b.last().id;}
  const tr=all.reverse().map(x=>({a:x.author.username,id:x.author.id,av:x.author.displayAvatarURL({size:64}),c:x.content,t:x.createdAt.toISOString(),f:[...x.attachments.values()].map(y=>y.url),e:x.embeds.length}));
  
  db.prepare("UPDATE tickets SET status='closed',closed_at=datetime('now'),closed_by=?,transcript=? WHERE id=?").run(i.user.username,JSON.stringify(tr),t.id);
  log(i.user.id,i.user.username,'ticket_close',`${LABEL[t.type]||t.type} of ${t.username} (#${t.id})`);
  
  // Update channel name: replace prefix with closed- (e.g. ticket-hama -> closed-hama)
  const currentName = i.channel.name;
  const newName = currentName.includes('-') ? currentName.replace(/^[^-_]+/, 'closed') : `closed-${currentName}`;
  await i.channel.setName(newName).catch(console.error);

  // Move channel and DENY user from viewing it
  await i.channel.setParent(TARGET_CLOSED_CATEGORY,{lockPermissions:false}).catch(console.error);
  await i.channel.permissionOverwrites.edit(t.user_id,{ViewChannel:false,SendMessages:false}).catch(()=>{});

  // Send DM to ticket owner
  try {
   const ownerUser = await client.users.fetch(t.user_id).catch(()=>null);
   if(ownerUser){
    await ownerUser.send({
     embeds: [
      new EmbedBuilder()
       .setColor(GREEN)
       .setTitle('🔒 Ticket Closed')
       .setDescription(`Your ticket **#${t.id}** has been closed by **${i.user.username}**.\nYou can no longer view this channel in the server.`)
       .setTimestamp()
     ]
    });
   }
  } catch(err) {
   console.error('Could not send DM to ticket owner:', err);
  }

  const row = new Row().addComponents(
   new ButtonBuilder()
    .setCustomId(`get_transcript_${t.id}`)
    .setLabel('📩 Save & Send Transcript')
    .setStyle(ButtonStyle.Primary)
  );

  return i.editReply({content:'🔒 Ticket closed successfully, renamed to closed, moved to closed category, and the user can no longer view it.', components: [row]});
 }

 if(i.isButton()&&(i.customId==='sg_up'||i.customId==='sg_down')){
  const s=db.prepare('SELECT * FROM suggestions WHERE message_id=?').get(i.message.id);if(!s)return i.reply({content:'Suggestion not found.',flags:EPH});
  const v=i.customId==='sg_up'?1:-1,cur=db.prepare('SELECT vote FROM suggestion_votes WHERE suggestion_id=? AND user_id=?').get(s.id,i.user.id),same=cur&&cur.vote===v;
  if(same)db.prepare('DELETE FROM suggestion_votes WHERE suggestion_id=? AND user_id=?').run(s.id,i.user.id);
  else db.prepare("REPLACE INTO suggestion_votes(suggestion_id,user_id,username,vote,created_at) VALUES(?,?,?,datetime('now'))").run(s.id,i.user.id,i.user.username,v);
  log(i.user.id,i.user.username,'suggestion_vote',`${same?'Removed vote':v>0?'Upvote 👍':'Downvote 👎'} on suggestion #${s.id} by ${s.author_name}: ${s.content.slice(0,80)}`);
  return i.update(sgMsg(s));
 }

 if(i.isButton()&&i.customId==='ticket_claim'){
  if(!isStaff(i))return i.reply({content:'Staff only.',flags:EPH});
  const t=db.prepare("SELECT * FROM tickets WHERE channel_id=? AND status='open'").get(i.channelId);
  if(!t)return i.reply({content:'This ticket is closed.',flags:EPH});
  if(t.claimed_by)return i.reply({content:`Already claimed by **${t.claimed_by}**.`,flags:EPH});
  db.prepare('UPDATE tickets SET claimed_by=? WHERE id=?').run(i.user.username,t.id);log(i.user.id,i.user.username,'ticket_claim',`${LABEL[t.type]||t.type} of ${t.username} (#${t.id})`);
  const rows=i.message.components.map(r=>Row.from(r)),b=rows[0].components.find(c=>c.data.custom_id==='ticket_claim');b.setDisabled(true).setLabel(`Claimed by ${i.user.username}`).setStyle(ButtonStyle.Success);
  await i.update({components:rows});return i.channel.send(`🙋 <@${i.user.id}> claimed this ticket.`);
 }

 if(i.isButton()&&i.customId==='ticket_dm'){
  if(!isStaff(i))return i.reply({content:'Staff only.',flags:EPH});
  const t=db.prepare('SELECT * FROM tickets WHERE channel_id=?').get(i.channelId);if(!t)return i.reply({content:'Ticket not found.',flags:EPH});
  const u=await client.users.fetch(t.user_id).catch(()=>null);
  const ok=u&&await u.send({embeds:[new EmbedBuilder().setColor(GREEN).setTitle('📩 Staff is waiting for you').setDescription(`<@${i.user.id}> is waiting for you in your ticket: <#${i.channelId}>`)]}).then(()=>true).catch(()=>false);
  if(ok)log(i.user.id,i.user.username,'ticket_dm',`DM sent to ${t.username} (#${t.id})`);
  return i.reply({content:ok?'✅ DM sent to the member.':'❌ Could not DM the member (DMs closed).',flags:EPH});
 }

 if(i.isButton()&&i.customId==='ticket_delete'){
  if(!isStaff(i))return i.reply({content:'Staff only.',flags:EPH});
  const t=db.prepare('SELECT * FROM tickets WHERE channel_id=?').get(i.channelId);
  if(!t||t.status!=='closed')return i.reply({content:'Close the ticket first.',flags:EPH});
  log(i.user.id,i.user.username,'ticket_delete',`${LABEL[t.type]||t.type} of ${t.username} (#${t.id})`);
  await i.reply('🗑️ Deleting this channel in 3 seconds. The transcript stays on the website.');setTimeout(()=>i.channel.delete().catch(console.error),3000);return;
 }

 if(i.isButton()&&i.customId==='cheater_add'){
  if(!canManage(i.user.id))return i.reply({content:'You do not have permission.',flags:EPH});
  const f=[['did','Discord ID'],['dname','Discord username'],['mname','MTA username'],['mid','MTA ID'],['proof','Proof link']];
  return i.showModal(new ModalBuilder().setCustomId('cheater_modal').setTitle('Add Cheater').addComponents(...f.map(([id,l])=>new Row().addComponents(new TextInputBuilder().setCustomId(id).setLabel(l).setStyle(TextInputStyle.Short).setRequired(true)))));
 }

 if(i.isModalSubmit()&&i.customId==='cheater_modal'){
  if(!canManage(i.user.id))return i.reply({content:'You do not have permission.',flags:EPH});
  const v=k=>i.fields.getTextInputValue(k).trim(),did=v('did').replace(/\D/g,''),proof=v('proof');
  if(!did)return i.reply({content:'Invalid Discord ID.',flags:EPH});
  if(!/^https?:\/\//i.test(proof))return i.reply({content:'Proof must be a link (http/https).',flags:EPH});
  if(db.prepare('SELECT 1 FROM cheaters WHERE discord_id=?').get(did))return i.reply({content:'This player is already in the list.',flags:EPH});
  await i.deferReply({flags:EPH});
  db.prepare("INSERT INTO cheaters(discord_id,discord_name,mta_name,mta_id,proof,added_by,added_by_name,created_at) VALUES(?,?,?,?,?,?,?,datetime('now'))").run(did,v('dname'),v('mname'),v('mid'),proof,i.user.id,i.user.username);
  log(i.user.id,i.user.username,'cheater_add',`${v('dname')} (${did}) / MTA ${v('mname')} #${v('mid')} / ${proof}`);
  const mem=await i.guild.members.fetch(did).catch(()=>null);let note='';
  if(mem&&cfg.cheaterRoleId)await mem.roles.add(cfg.cheaterRoleId).catch(e=>note+='\n⚠️ Could not give the role (check bot role position).');
  else note+='\nℹ️ Player is not in the server: the role will be given when they join.';
  const u=mem?.user||await client.users.fetch(did).catch(()=>null);
  await u?.send({embeds:[new EmbedBuilder().setColor(0xff3333).setTitle('🚨 You were added to the cheaters list').addFields({name:'MTA username',value:v('mname'),inline:true},{name:'MTA ID',value:v('mid'),inline:true},{name:'Proof',value:proof}).setDescription('If you think this is a mistake, open a **Ban Appeal** ticket.')]}).catch(()=>{note+='\nℹ️ Could not DM the player.'});
  return i.editReply('✅ Cheater added to the list.'+note);
 }
}catch(e){console.error(e);if(!i.replied&&!i.deferred)i.reply({content:'Something went wrong.',flags:EPH}).catch(()=>{});else if(i.deferred)i.editReply('Something went wrong.').catch(()=>{});}});

client.on(Events.GuildMemberAdd,m=>{if(m.guild.id===cfg.guildId&&cfg.cheaterRoleId&&db.prepare('SELECT 1 FROM cheaters WHERE discord_id=?').get(m.id))m.roles.add(cfg.cheaterRoleId).catch(console.error);});
client.on(Events.GuildMemberUpdate,(o,n)=>{const r=cfg.cheaterRoleId;if(r&&o.roles?.cache.has(r)&&!n.roles.cache.has(r)&&db.prepare('SELECT 1 FROM cheaters WHERE discord_id=?').get(n.id))n.roles.add(r).catch(console.error);});

const AL=AuditLogEvent,fld=(name,value,inline=false)=>({name,value:String(value||'—').slice(0,1000),inline});
async function sendLog(ev,guild,build){if(!guild||guild.id!==cfg.guildId)return;const s=db.prepare('SELECT * FROM discord_logs WHERE event=?').get(ev);if(!s||!s.enabled||!s.channel_id)return;
 const ch=await guild.channels.fetch(s.channel_id).catch(()=>null);if(!ch)return;const e=new EmbedBuilder().setColor(s.color||'#00ff66').setTimestamp();
 try{await build(e);}catch(x){return console.error(x);}ch.send({embeds:[e]}).catch(console.error);}
async function who(guild,type,id){await new Promise(r=>setTimeout(r,1500));try{const l=await guild.fetchAuditLogs({type,limit:8});const x=l.entries.find(y=>y.targetId===id&&Date.now()-y.createdTimestamp<20000);return x?.executor?`<@${x.executor.id}>`:'Unknown';}catch{return 'Unknown (give the bot View Audit Log)';}}
const mention=u=>u?`<@${u.id}> ${u.username}`:'Unknown';
client.on(Events.ChannelCreate,c=>sendLog('channel_create',c.guild,async e=>e.setTitle('📁 Channel created').addFields(fld('Channel',`<#${c.id}> \`${c.name}\``),fld('Type',ChannelType[c.type],true),fld('By',await who(c.guild,AL.ChannelCreate,c.id),true))));
client.on(Events.ChannelDelete,c=>sendLog('channel_delete',c.guild,async e=>e.setTitle('🗑️ Channel deleted').addFields(fld('Channel',`\`${c.name}\``),fld('Type',ChannelType[c.type],true),fld('By',await who(c.guild,AL.ChannelDelete,c.id),true))));
client.on(Events.ChannelUpdate,(o,n)=>{if(!n.guild)return;const d=[];if(o.name!==n.name)d.push(fld('Name',`\`${o.name}\` → \`${n.name}\``));if(o.topic!==n.topic)d.push(fld('Topic',`${o.topic||'—'} → ${n.topic||'—'}`));
 if(o.parentId!==n.parentId)d.push(fld('Category',`${o.parentId?`<#${o.parentId}>`:'none'} → ${n.parentId?`<#${n.parentId}>`:'none'}`));if(!d.length)return;
 sendLog('channel_update',n.guild,async e=>e.setTitle('✏️ Channel updated').addFields(fld('Channel',`<#${n.id}>`),...d,fld('By',await who(n.guild,AL.ChannelUpdate,n.id),true)));});
client.on(Events.GuildRoleCreate,r=>sendLog('role_create',r.guild,async e=>e.setTitle('🎭 Role created').addFields(fld('Role',`<@&${r.id}> \`${r.name}\``),fld('By',await who(r.guild,AL.RoleCreate,r.id),true))));
client.on(Events.GuildRoleDelete,r=>sendLog('role_delete',r.guild,async e=>e.setTitle('🗑️ Role deleted').addFields(fld('Role',`\`${r.name}\``),fld('By',await who(r.guild,AL.RoleDelete,r.id),true))));
client.on(Events.GuildRoleUpdate,(o,n)=>{const d=[];if(o.name!==n.name)d.push(fld('Name',`\`${o.name}\` → \`${n.name}\``));if(o.color!==n.color)d.push(fld('Color',`#${o.color.toString(16)} → #${n.color.toString(16)}`));
 if(o.permissions.bitfield!==n.permissions.bitfield)d.push(fld('Permissions','changed'));if(!d.length)return;
 sendLog('role_update',n.guild,async e=>e.setTitle('🎭 Role updated').addFields(fld('Role',`<@&${n.id}>`),...d,fld('By',await who(n.guild,AL.RoleUpdate,n.id),true)));});
client.on(Events.GuildMemberAdd,m=>sendLog('member_join',m.guild,e=>e.setTitle('📥 Member joined').setThumbnail(m.user.displayAvatarURL()).addFields(fld('Member',mention(m.user)),fld('ID',m.id,true),fld('Account created',`<t:${Math.floor(m.user.createdTimestamp/1000)}:R>`,true))));
client.on(Events.GuildMemberRemove,m=>sendLog('member_leave',m.guild,e=>e.setTitle('📤 Member left').addFields(fld('Member',mention(m.user)),fld('ID',m.id,true),fld('Roles',[...m.roles.cache.filter(r=>r.id!==m.guild.id).values()].map(r=>`<@&${r.id}>`).join(' ')))));
client.on(Events.GuildBanAdd,b=>sendLog('member_ban',b.guild,async e=>e.setTitle('🔨 Member banned').addFields(fld('User',mention(b.user)),fld('Reason',b.reason),fld('By',await who(b.guild,AL.MemberBanAdd,b.user.id),true))));
client.on(Events.GuildBanRemove,b=>sendLog('member_unban',b.guild,async e=>e.setTitle('♻️ Member unbanned').addFields(fld('User',mention(b.user)),fld('By',await who(b.guild,AL.MemberBanRemove,b.user.id),true))));
client.on(Events.GuildMemberUpdate,(o,n)=>{if(o.partial)return;const d=[];if(o.nickname!==n.nickname)d.push(fld('Nickname',`${o.nickname||'—'} → ${n.nickname||'—'}`));
 const add=n.roles.cache.filter(r=>!o.roles.cache.has(r.id)),rem=o.roles.cache.filter(r=>!n.roles.cache.has(r.id));
 if(add.size)d.push(fld('Roles added',[...add.values()].map(r=>`<@&${r.id}>`).join(' ')));if(rem.size)d.push(fld('Roles removed',[...rem.values()].map(r=>`<@&${r.id}>`).join(' ')));if(!d.length)return;
 sendLog('member_update',n.guild,async e=>e.setTitle('👤 Member updated').addFields(fld('Member',mention(n.user)),...d,fld('By',await who(n.guild,(add.size||rem.size)?AL.MemberRoleUpdate:AL.MemberUpdate,n.id),true)));});
client.on(Events.MessageDelete,m=>{if(!m.guild||m.author?.id===client.user.id)return;sendLog('message_delete',m.guild,e=>e.setTitle('🗑️ Message deleted').addFields(fld('Author',m.author?mention(m.author):'Unknown (not cached)'),fld('Channel',`<#${m.channelId}>`,true),fld('Content',m.content||'(empty or not cached)'),...(m.attachments?.size?[fld('Attachments',[...m.attachments.values()].map(a=>a.url).join('\n'))]:[])));});
client.on(Events.MessageUpdate,(o,n)=>{if(!n.guild||n.author?.bot||o.content==null||o.content===n.content)return;sendLog('message_edit',n.guild,e=>e.setTitle('✏️ Message edited').setURL(n.url).addFields(fld('Author',mention(n.author)),fld('Channel',`<#${n.channelId}>`,true),fld('Before',o.content),fld('After',n.content)));});
client.on(Events.VoiceStateUpdate,(o,n)=>{if(o.channelId===n.channelId)return;const t=!o.channelId?`🔊 joined <#${n.channelId}>`:!n.channelId?`🔇 left <#${o.channelId}>`:`🔀 moved <#${o.channelId}> → <#${n.channelId}>`;sendLog('voice_state',n.guild,e=>e.setTitle('🎙️ Voice activity').setDescription(`<@${n.id}> ${t}`));});

client.on(Events.MessageCreate,async m=>{try{if(m.author.bot||!m.guild||m.guild.id!==cfg.guildId)return;
 touch(m.author);addAct(m.author.id,'messages',1);

 // Command: -close
 if(m.content.trim() === '-close'){
  if(m.channel.parentId !== ALLOWED_CLOSE_CATEGORY){
   return m.reply('❌ You can only use `-close` inside tickets in the designated category!');
  }
  
  let t = db.prepare("SELECT * FROM tickets WHERE channel_id=? AND status='open'").get(m.channelId);
  if(!t){
   t = db.prepare("SELECT * FROM tickets WHERE channel_id=?").get(m.channelId);
   if(!t){
    db.prepare("INSERT INTO tickets(channel_id,user_id,username,type,status,created_at) VALUES(?,?,?,?,?,datetime('now'))").run(m.channelId,m.author.id,m.author.username,'support','open');
    t = db.prepare("SELECT * FROM tickets WHERE channel_id=?").get(m.channelId);
   }else{
    db.prepare("UPDATE tickets SET status='open' WHERE channel_id=?").run(m.channelId);
   }
  }

  const ok = m.author.id === t.user_id || isStaff(m);
  if(!ok) return m.reply('❌ You do not have permission to close this ticket.');

  let all=[],before;
  for(let n=0;n<10;n++){const b=await m.channel.messages.fetch({limit:100,before});if(!b.size)break;all.push(...b.values());before=b.last().id;}
  const tr=all.reverse().map(x=>({a:x.author.username,id:x.author.id,av:x.author.displayAvatarURL({size:64}),c:x.content,t:x.createdAt.toISOString(),f:[...x.attachments.values()].map(y=>y.url),e:x.embeds.length}));
  
  db.prepare("UPDATE tickets SET status='closed',closed_at=datetime('now'),closed_by=?,transcript=? WHERE id=?").run(m.author.username,JSON.stringify(tr),t.id);
  log(m.author.id,m.author.username,'ticket_close',`${LABEL[t.type]||t.type} of ${t.username} (#${t.id})`);

  // Rename channel prefix to closed- while keeping the rest (e.g. ticket-hama -> closed-hama)
  const currentName = m.channel.name;
  const newName = currentName.includes('-') ? currentName.replace(/^[^-_]+/, 'closed') : `closed-${currentName}`;
  await m.channel.setName(newName).catch(console.error);

  // Move channel and DENY user from viewing it
  await m.channel.setParent(TARGET_CLOSED_CATEGORY,{lockPermissions:false}).catch(console.error);
  await m.channel.permissionOverwrites.edit(t.user_id,{ViewChannel:false,SendMessages:false}).catch(()=>{});

  // Send DM to ticket owner
  try {
   const ownerUser = await client.users.fetch(t.user_id).catch(()=>null);
   if(ownerUser){
    await ownerUser.send({
     embeds: [
      new EmbedBuilder()
       .setColor(GREEN)
       .setTitle('🔒 Ticket Closed')
       .setDescription(`Your ticket **#${t.id}** has been closed by **${m.author.username}**.\nYou can no longer view this channel in the server.`)
       .setTimestamp()
     ]
    });
   }
  } catch(err) {
   console.error('Could not send DM to ticket owner:', err);
  }

  const row = new Row().addComponents(
   new ButtonBuilder()
    .setCustomId(`get_transcript_${t.id}`)
    .setLabel('📩 Save & Send Transcript')
    .setStyle(ButtonStyle.Primary)
  );

  return m.channel.send({
   content: '🔒 Ticket closed successfully, renamed to closed, moved to closed category, and the user can no longer view it.',
   components: [row]
  });
 }

 // Command: -sdm
 if(m.content.trim() === '-sdm'){
  if(!isStaff(m)) return m.reply('❌ Only staff members can use `-sdm`!');
  
  let t = db.prepare("SELECT * FROM tickets WHERE channel_id=?").get(m.channelId);
  if(!t){
   db.prepare("INSERT INTO tickets(channel_id,user_id,username,type,status,created_at) VALUES(?,?,?,?,?,datetime('now'))").run(m.channelId,m.author.id,m.author.username,'support','open');
   t = db.prepare("SELECT * FROM tickets WHERE channel_id=?").get(m.channelId);
  }

  const user = await client.users.fetch(t.user_id).catch(()=>null);
  if(!user) return m.reply('❌ Ticket owner not found.');

  const embed = new EmbedBuilder()
   .setColor(GREEN)
   .setTitle('📩 Staff is waiting for you')
   .setDescription(`<@${m.author.id}> is waiting for you in your ticket: <#${m.channelId}>`);

  try {
   await user.send({ embeds: [embed] });
   return m.reply(`✅ Direct message successfully sent to <@${t.user_id}>!`);
  } catch(e) {
   return m.reply('❌ Could not send a DM to the user (Their DMs might be closed).');
  }
 }

 if(m.channelId!==SUG)return;const text=m.content.trim().slice(0,1800);if(!text)return;
 const r=db.prepare("INSERT INTO suggestions(channel_id,author_id,author_name,content,created_at) VALUES(?,?,?,?,datetime('now'))").run(m.channelId,m.author.id,m.author.username,text);
 const s=db.prepare('SELECT * FROM suggestions WHERE id=?').get(Number(r.lastInsertRowid));const sent=await m.channel.send(sgMsg(s));
 db.prepare('UPDATE suggestions SET message_id=? WHERE id=?').run(sent.id,s.id);log(m.author.id,m.author.username,'suggestion_create',`#${s.id}: ${text.slice(0,120)}`);m.delete().catch(()=>{});
}catch(e){console.error(e)}});

client.on(Events.VoiceStateUpdate,(o,n)=>{const id=n.id;if(n.member?.user?.bot)return;touch(n.member?.user);
 if(n.channelId&&!VJ.has(id))VJ.set(id,Date.now());
 if(!n.channelId&&VJ.has(id)){const sec=Math.round((Date.now()-VJ.get(id))/1000);VJ.delete(id);if(sec>0)addAct(id,'voice',sec);}});

setInterval(()=>{const now=Date.now();for(const [id,t] of VJ){const sec=Math.round((now-t)/1000);if(sec>=60){addAct(id,'voice',sec);VJ.set(id,now)}}},60000);

const INV=new Map();
async function loadInvites(g){try{const l=await g.invites.fetch(),old=new Map(INV);INV.clear();l.forEach(i=>INV.set(i.code,{uses:i.uses||0,inviter:i.inviter?.id||null}));return {old}}catch(e){return null}}
client.once(Events.ClientReady,async()=>{const g=client.guilds.cache.get(cfg.guildId);if(!g)return;if(!await loadInvites(g))return console.log('Invite tracking needs the "Manage Server" permission');
 if(!getS('inv_baseline')){const sums=new Map();for(const v of INV.values())if(v.inviter)sums.set(v.inviter,(sums.get(v.inviter)||0)+v.uses);
  for(const [id,u] of sums){const tracked=db.prepare('SELECT COUNT(*) c FROM invite_joins WHERE inviter_id=?').get(id).c;db.prepare('REPLACE INTO invite_baseline(inviter_id,uses) VALUES(?,?)').run(id,Math.max(0,u-tracked))}
  setS('inv_baseline','1');console.log('Invite history baseline saved')}});
client.on(Events.InviteCreate,i=>{if(i.guild?.id===cfg.guildId)INV.set(i.code,{uses:i.uses||0,inviter:i.inviter?.id||null})});
client.on(Events.GuildMemberAdd,async m=>{if(m.guild.id!==cfg.guildId||m.user.bot)return;touch(m.user);let inviter=null,code=null;
 const r=await loadInvites(m.guild);
 if(r){let used=[...INV].find(([c,v])=>v.uses>(r.old.get(c)?.uses||0));if(!used)used=[...r.old].find(([c])=>!INV.has(c));if(used){code=used[0];inviter=used[1].inviter||null;}}
 db.prepare("INSERT INTO invite_joins(user_id,inviter_id,code,joined_at) VALUES(?,?,?,datetime('now'))").run(m.id,inviter,code);});
client.on(Events.GuildMemberRemove,m=>{if(m.guild.id===cfg.guildId)db.prepare("UPDATE invite_joins SET left_at=datetime('now') WHERE id=(SELECT id FROM invite_joins WHERE user_id=? AND left_at IS NULL ORDER BY id DESC LIMIT 1)").run(m.id)});

const DAY=ms=>new Date(ms).toISOString().slice(0,10);
async function scanChannel(ch,cutoff,onBatch){const counts=new Map(),people=new Map();let before,n=0;
 for(;;){const b=await ch.messages.fetch({limit:100,before});if(!b.size)break;
  for(const m of b.values()){if(m.createdTimestamp>=cutoff||m.author.bot||m.webhookId||m.system)continue;const k=m.author.id+'|'+DAY(m.createdTimestamp);counts.set(k,(counts.get(k)||0)+1);people.set(m.author.id,m.author)}
  n+=b.size;before=b.last().id;if(onBatch)onBatch(b.size)}
 return {counts,people,n};}
function commitChannel(chId,counts,people,scanned){const up=db.prepare('INSERT INTO activity(discord_id,day,messages) VALUES(?,?,?) ON CONFLICT(discord_id,day) DO UPDATE SET messages=messages+excluded.messages'),
 mem=db.prepare('INSERT INTO members(discord_id,username,avatar) VALUES(?,?,?) ON CONFLICT(discord_id) DO UPDATE SET username=excluded.username,avatar=excluded.avatar');
 const done=new Set(JSON.parse(getS('bf_done')||'[]'));done.add(chId);
 db.exec('BEGIN');try{for(const [k,c] of counts){const [id,day]=k.split('|');up.run(id,day,c)}for(const u of people.values())mem.run(u.id,u.globalName||u.username,u.displayAvatarURL({size:64}));setS('bf_done',JSON.stringify([...done]));setS('bf_scanned',String(scanned));db.exec('COMMIT')}catch(e){db.exec('ROLLBACK');throw e}}
async function backfill(){if(cfg.backfill===false||getS('bf_state')==='done')return;const g=await client.guilds.fetch(cfg.guildId).catch(()=>null);if(!g)return;
 if(!getS('bf_cutoff')){setS('bf_cutoff',String(Date.now()));db.prepare('UPDATE activity SET messages=0').run()}
 const cutoff=Number(getS('bf_cutoff')),done=new Set(JSON.parse(getS('bf_done')||'[]'));let scanned=Number(getS('bf_scanned')||0);
 const status=(o)=>setS('bf_status',JSON.stringify({scanned,...o}));status({state:'running',step:'members'});
 try{const all=await g.members.fetch(),ins=db.prepare('INSERT INTO members(discord_id,username,avatar) VALUES(?,?,?) ON CONFLICT(discord_id) DO UPDATE SET username=excluded.username,avatar=excluded.avatar'),
  has=db.prepare('SELECT 1 FROM invite_joins WHERE user_id=?'),insJ=db.prepare('INSERT INTO invite_joins(user_id,inviter_id,code,joined_at) VALUES(?,NULL,NULL,?)');
  db.exec('BEGIN');for(const m of all.values()){if(m.user.bot)continue;ins.run(m.id,m.user.globalName||m.user.username,m.user.displayAvatarURL({size:64}));if(!has.get(m.id)&&m.joinedTimestamp)insJ.run(m.id,new Date(m.joinedTimestamp).toISOString().slice(0,19).replace('T',' '))}db.exec('COMMIT')}
 catch(e){try{db.exec('ROLLBACK')}catch{}console.error('Members import failed:',e.message)}
 const TYPES=[ChannelType.GuildText,ChannelType.GuildAnnouncement,ChannelType.GuildVoice,ChannelType.GuildStageVoice];let list=[...(await g.channels.fetch()).values()].filter(c=>c&&TYPES.includes(c.type));
 for(const c of [...list]){if(!c.threads)continue;try{(await c.threads.fetchActive()).threads.forEach(t=>list.push(t));(await c.threads.fetchArchived({type:'public',fetchAll:true})).threads.forEach(t=>list.push(t))}catch{}}
 console.log(`History import: ${list.length} channels to read (this can take a while, the bot keeps working)`);
 for(const ch of list){if(done.has(ch.id))continue;status({state:'running',step:'messages',channel:ch.name});
  try{const r=await scanChannel(ch,cutoff,k=>{scanned+=k;if(scanned%1000<k)status({state:'running',step:'messages',channel:ch.name})});commitChannel(ch.id,r.counts,r.people,scanned)}
  catch(e){console.log(`Skipped #${ch.name}: ${e.message}`);try{commitChannel(ch.id,new Map(),new Map(),scanned)}catch{}}
  done.add(ch.id)}
 setS('bf_state','done');status({state:'done'});console.log(`History import finished: ${scanned} messages scanned`);}
client.once(Events.ClientReady,()=>{backfill().catch(e=>console.error('History import error:',e))});

const siteUrl=(cfg.siteUrl||'http://127.0.0.1:8000').replace(/\/$/,''),rtok=require('crypto').createHash('sha256').update(cfg.token+'|report').digest('hex');
const pingReport=()=>fetch(`${siteUrl}/report-cron.php?t=${rtok}`).then(r=>r.text()).then(t=>{if(/^sent/.test(t))console.log('Daily report: '+t)}).catch(()=>{});
client.once(Events.ClientReady,()=>{setTimeout(pingReport,120000);setInterval(pingReport,3600000)});

if(require.main===module)client.login(cfg.token);
module.exports={scanChannel,commitChannel,db,setS,getS};