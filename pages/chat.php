<?php
require_once __DIR__.'/../includes/authorization.php';
require_roles(['customer','seller','admin']);
require_once __DIR__.'/../includes/Repositories/ChatRepository.php';

$chat=new ChatRepository();
$user=current_user();
$userId=(int)$user['id'];
$role=(string)$user['role'];
$action=(string)($_GET['chat_action']??'');
$conversationId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
$newMode=(string)($_GET['new']??'');
$isAjax=strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest';

$findOpenSupportId=static function(int $uid,string $userRole):int{
    if(!in_array($userRole,['customer','seller'],true))return 0;
    try{
        $column=$userRole==='customer'?'customer_id':'seller_id';
        $st=db()->prepare("SELECT id FROM chat_conversations
            WHERE type='support' AND status='open' AND {$column}=?
            ORDER BY COALESCE(last_message_at,updated_at,created_at) DESC,id DESC LIMIT 1");
        $st->execute([$uid]);
        return(int)($st->fetchColumn()?:0);
    }catch(Throwable){return 0;}
};

if($newMode==='support'&&in_array($role,['customer','seller'],true)){
    $existing=$findOpenSupportId($userId,$role);
    if($existing>0)redirect(page_url('chat',['id'=>$existing]));
}

if($action==='messages'){
    header('Content-Type: application/json; charset=utf-8');
    try{
        $after=filter_input(INPUT_GET,'after',FILTER_VALIDATE_INT)?:0;
        $selected=$chat->find($conversationId,$userId,$role);
        if(!$selected)throw new RuntimeException('Percakapan tidak ditemukan.');
        $chat->markRead($conversationId,$userId,$role);
        echo json_encode([
            'ok'=>true,
            'messages'=>$chat->messages($conversationId,$userId,$role,$after),
            'read_upto'=>$chat->readUpto($conversationId,$userId,$role),
        ],JSON_UNESCAPED_UNICODE);
    }catch(Throwable $e){
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if(is_post()){
    $redirectId=$conversationId;

    if(!verify_csrf()){
        if($isAjax){
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(419);
            echo json_encode(['ok'=>false,'message'=>'Sesi formulir tidak valid. Muat ulang halaman.'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash('error','Sesi formulir tidak valid.');
        redirect(page_url('chat',$redirectId?['id'=>$redirectId]:[]));
    }

    $formAction=(string)($_POST['chat_action']??'');

    try{
        if($formAction==='start_marketplace'){
            if($role!=='customer')throw new RuntimeException('Aksi tidak diizinkan.');
            $productId=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT);
            if(!$productId)throw new RuntimeException('Produk tidak valid.');
            $redirectId=$chat->startMarketplace($userId,$role,(int)$productId);
            redirect(page_url('chat',['id'=>$redirectId]));
        }

        if($formAction==='start_support'){
            $subject=trim((string)($_POST['subject']??''));
            $body=trim((string)($_POST['body']??''));

            $existing=$findOpenSupportId($userId,$role);
            if($existing>0){
                $redirectId=$existing;
                $chat->sendMessage($redirectId,$userId,$role,$body);
            }else{
                $redirectId=$chat->startSupport($userId,$role,$subject,$body);
            }

            redirect(page_url('chat',['id'=>$redirectId]));
        }

        if($formAction==='send'){
            $id=filter_input(INPUT_POST,'conversation_id',FILTER_VALIDATE_INT);
            if(!$id)throw new RuntimeException('Percakapan tidak valid.');

            $messageId=$chat->sendMessage((int)$id,$userId,$role,(string)($_POST['body']??''));

            if($isAjax){
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok'=>true,
                    'message'=>$chat->findMessage($messageId,(int)$id,$userId,$role),
                    'read_upto'=>$chat->readUpto((int)$id,$userId,$role),
                ],JSON_UNESCAPED_UNICODE);
                exit;
            }

            redirect(page_url('chat',['id'=>(int)$id]));
        }

        if($formAction==='close'){
            $id=filter_input(INPUT_POST,'conversation_id',FILTER_VALIDATE_INT);
            if(!$id)throw new RuntimeException('Percakapan tidak valid.');
            $chat->closeConversation((int)$id,$userId,$role);
            flash('success','Pengaduan ditutup.');
            redirect(page_url('chat',['id'=>(int)$id]));
        }
    }catch(Throwable $e){
        if($isAjax){
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash('error',$e->getMessage());
        redirect(page_url('chat',$redirectId?['id'=>$redirectId]:[]));
    }
}

/* Sesuai UX yang diminta: saat pusat Chat dibuka, badge navbar langsung hilang. */
try{$chat->markAllReadForUser($userId,$role);}catch(Throwable){}

$chatLoadError=null;
try{
    $conversations=$chat->listForUser($userId,$role);
    $selected=$conversationId?$chat->find($conversationId,$userId,$role):null;
    if($selected)$chat->markRead($conversationId,$userId,$role);
    $messages=$selected?$chat->messages($conversationId,$userId,$role):[];
}catch(Throwable $e){
    $conversations=[];$selected=null;$messages=[];
    $chatLoadError='Chat belum dapat dimuat. Pastikan tabel chat sudah tersedia.';
}

$pageTitle=$role==='admin'?'Chat Pengaduan':'Chat';
require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/chat.css?v=20260924-1">
<link rel="stylesheet" href="<?=e(APP_URL)?>/assets/css/chat-receipts-polish.css?v=20260924-1">

<!-- Fallback yang dijamin: desainnya SAMA dengan chat.css, bukan desain baru. -->
<style id="koscycle-chat-guaranteed-style">
.chat-shell{display:grid;grid-template-columns:340px minmax(0,1fr);gap:1rem;min-height:650px}.chat-sidebar,.chat-main{min-width:0;overflow:hidden}.chat-sidebar{display:flex;flex-direction:column}.chat-sidebar-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px;border-bottom:1px solid var(--line)}.chat-list-title{font:600 1.15rem/1 'Space Grotesk';margin:.35rem 0 0}.chat-count-badge{display:grid;place-items:center;min-width:28px;height:28px;padding:0 8px;border-radius:20px;background:var(--coral);color:#fff;font-size:.72rem;font-weight:700}.chat-support-button{display:flex;align-items:center;gap:11px;margin:12px;border:1px solid rgba(40,125,108,.12);border-radius:14px;padding:12px;background:rgba(168,216,200,.20);color:var(--ink);text-decoration:none}.chat-support-button>i:first-child{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#fff;color:var(--teal)}.chat-support-button span{flex:1;min-width:0}.chat-support-button strong,.chat-support-button small{display:block}.chat-support-button small{color:var(--muted);font-size:.7rem;margin-top:2px}.chat-conversation-list{padding:0 8px 10px;overflow:auto}.chat-conversation-item{display:flex;align-items:center;gap:10px;padding:11px;border-radius:13px;color:var(--ink);text-decoration:none}.chat-conversation-item:hover,.chat-conversation-item.is-active{background:rgba(168,216,200,.18)}.chat-avatar{flex:0 0 40px;width:40px;height:40px;display:grid;place-items:center;border-radius:50%;background:rgba(168,216,200,.35);color:var(--teal)}.chat-avatar.is-support{background:rgba(239,128,95,.12);color:#a65039}.chat-avatar-lg{width:46px;height:46px;flex-basis:46px}.chat-conversation-copy{min-width:0;flex:1}.chat-conversation-copy strong,.chat-conversation-copy small,.chat-conversation-copy span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.chat-conversation-copy strong{font-size:.86rem}.chat-conversation-copy small{color:var(--teal);font-size:.67rem;font-weight:700;margin:2px 0}.chat-conversation-copy span{color:var(--muted);font-size:.72rem}.chat-unread-dot{display:grid;place-items:center;min-width:21px;height:21px;border-radius:50%;background:var(--coral);color:#fff;font-size:.62rem;font-weight:700}.chat-empty-list{padding:36px 18px;text-align:center;color:var(--muted)}.chat-empty-list i{display:block;color:var(--teal);font-size:1.6rem;margin-bottom:8px}.chat-empty-list strong,.chat-empty-list span{display:block}.chat-empty-list strong{color:var(--ink)}.chat-empty-list span{font-size:.76rem;margin-top:3px}.chat-main{display:flex;flex-direction:column;background:rgba(255,254,250,.82)}.chat-main-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--line)}.chat-main-person{display:flex;align-items:center;gap:10px;min-width:0}.chat-main-person>div{min-width:0}.chat-main-person strong,.chat-main-person small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.chat-main-person small{font-size:.72rem;color:var(--muted)}.chat-product-context{display:flex;align-items:flex-start;gap:10px;padding:9px 16px;border-bottom:1px solid var(--line);background:rgba(168,216,200,.11)}.chat-product-context i{color:var(--teal);margin-top:2px}.chat-product-context small,.chat-product-context strong,.chat-product-context p{display:block}.chat-product-context small{font-size:.61rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em}.chat-product-context strong{font-size:.76rem}.chat-product-context p{font-size:.72rem;color:#53645f;line-height:1.5;margin:5px 0 0;max-width:720px;white-space:normal}.chat-messages{flex:1;overflow:auto;padding:18px;min-height:360px;max-height:520px;scroll-behavior:smooth;background:linear-gradient(180deg,rgba(245,245,239,.45),rgba(255,254,250,.15))}.chat-message-row{display:flex;margin-bottom:10px}.chat-message-row.is-me{justify-content:flex-end}.chat-message-bubble{max-width:min(78%,540px);padding:9px 11px;border-radius:16px;background:#fff;border:1px solid rgba(23,33,31,.07);font-size:.82rem;line-height:1.5;overflow-wrap:anywhere}.chat-message-row.is-me .chat-message-bubble{background:var(--teal);color:#fff;border-color:transparent;border-bottom-right-radius:5px}.chat-message-row.is-them .chat-message-bubble{border-bottom-left-radius:5px}.chat-sender-label{display:block;color:var(--teal);font-size:.65rem;font-weight:700;margin-bottom:3px}.chat-composer{display:flex;align-items:flex-end;gap:8px;padding:12px;border-top:1px solid var(--line);background:rgba(255,254,250,.95)}.chat-composer textarea{resize:none;min-height:48px;max-height:120px}.chat-send-button{width:48px;height:48px;flex:0 0 48px;padding:0;display:grid;place-items:center}.chat-ajax-status{min-height:0;padding:0 14px 7px;text-align:right;color:#a65039;font-size:.68rem}.chat-closed-note{padding:13px;text-align:center;color:var(--muted);font-size:.78rem;border-top:1px solid var(--line)}.chat-empty-state{min-height:560px;padding:42px 26px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}.chat-compose-state{justify-content:flex-start;align-items:stretch}.chat-hero-icon{display:grid;place-items:center;width:68px;height:68px;border-radius:50%;background:rgba(168,216,200,.32);color:var(--teal);font-size:1.8rem;margin:0 auto 15px}.chat-empty-state h3{font:600 1.3rem 'Space Grotesk'}.chat-empty-state p{max-width:430px;color:var(--muted);font-size:.82rem}.chat-support-form{max-width:620px;width:100%;margin:20px auto 0}.chat-mobile-back{display:none;color:var(--teal);text-decoration:none;font-weight:700;font-size:.78rem}
@media(max-width:991px){.chat-shell{grid-template-columns:300px minmax(0,1fr)}.chat-message-bubble{max-width:85%}}
@media(max-width:767px){.chat-page .container{padding-left:12px;padding-right:12px}.chat-shell{display:block;min-height:0}.chat-shell.has-selection .chat-sidebar,.chat-shell.is-compose .chat-sidebar{display:none}.chat-shell:not(.has-selection):not(.is-compose) .chat-main{display:none}.chat-sidebar{max-height:none;min-height:480px}.chat-main{min-height:calc(100dvh - 155px);border-radius:16px}.chat-mobile-back{display:inline-flex;align-items:center;gap:5px;flex:0 0 auto}.chat-main-head{padding:11px}.chat-main-person{gap:8px}.chat-avatar-lg{width:38px;height:38px;flex-basis:38px}.chat-product-context{padding:8px 11px}.chat-messages{min-height:calc(100dvh - 330px);max-height:calc(100dvh - 270px);padding:13px 10px}.chat-message-bubble{max-width:90%;font-size:.79rem}.chat-composer{position:sticky;bottom:0;padding:9px}.chat-empty-state{min-height:500px;padding:30px 16px}.chat-compose-state .chat-mobile-back{align-self:flex-start;margin-bottom:14px}.chat-support-button{margin:10px}.chat-sidebar-head{padding:15px}}


.chat-page .chat-message-meta{display:flex;align-items:center;justify-content:flex-end;gap:5px;margin-top:4px;min-height:12px}.chat-page .chat-message-bubble time{display:inline-block;margin:0;font-size:.61rem;line-height:1;opacity:.65}.chat-page .chat-read-receipt{display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;line-height:1;opacity:.92;transition:color .18s ease,transform .18s ease}.chat-page .chat-read-receipt.is-sent{color:rgba(255,255,255,.78)}.chat-page .chat-read-receipt.is-read{color:#7ee0cf;opacity:1}.chat-page .chat-message-row.is-them .chat-read-receipt{color:var(--teal)}.chat-page .chat-read-receipt.is-read i{transform:translateX(0)}.chat-page .chat-ajax-status{min-height:0;padding:0 16px 7px;text-align:right;color:#a65039;font-size:.7rem}.chat-page .chat-composer button:disabled{opacity:.7;cursor:wait}.chat-page .chat-read-receipt.is-incoming{color:var(--teal);opacity:1}.chat-page .chat-message-row.is-them .chat-message-meta{opacity:.9}


.chat-page .chat-conversation-item{border:1px solid transparent}
.chat-page .chat-conversation-item.is-active{border-color:rgba(40,125,108,.15)}
.chat-page .chat-product-context>div{min-width:0}
.chat-page .chat-product-context p{overflow-wrap:anywhere}
@media(max-width:767px){
  .chat-page{padding-top:92px!important}
  .chat-page .section-heading h2{font-size:clamp(2rem,10vw,2.8rem)!important}
  .chat-page .chat-sidebar,.chat-page .chat-main{width:100%}
}
</style>

<section class="section-padding page-section chat-page">
<div class="container">
    <div class="page-toolbar"><?=back_link(dashboard_page_for_role($role),'Kembali ke dashboard')?></div>

    <div class="section-heading mb-4">
        <span class="eyebrow"><i class="bi bi-chat-dots me-1"></i><?=$role==='admin'?'Admin':'Pesan'?></span>
        <h2><?=$role==='admin'?'Chat <em>pengaduan.</em>':'Live chat <em>KosCycle.</em>'?></h2>
        <p class="text-muted mb-0">Pesan diperbarui otomatis tanpa refresh. Centang dua berarti pesanmu sudah dibaca lawan chat.</p>
    </div>

    <?php if($chatLoadError):?><div class="alert alert-danger glass-alert mb-4"><?=e($chatLoadError)?></div><?php endif;?>
    <?php if($m=flash('success')):?><div class="alert alert-success glass-alert mb-4"><?=e($m)?></div><?php endif;?>
    <?php if($m=flash('error')):?><div class="alert alert-danger glass-alert mb-4"><?=e($m)?></div><?php endif;?>

    <div class="chat-shell <?=$selected?'has-selection':''?> <?=$newMode==='support'?'is-compose':''?>">
        <aside class="chat-sidebar glass-card">
            <div class="chat-sidebar-head">
                <div>
                    <span class="eyebrow">Inbox</span>
                    <h3 class="chat-list-title"><?=$role==='admin'?'Pengaduan pengguna':'Percakapanmu'?></h3>
                </div>
            </div>

            <?php if(in_array($role,['customer','seller'],true)):?>
                <a class="chat-support-button" data-no-preloader="1" href="<?=e(page_url('chat',['new'=>'support']))?>">
                    <i class="bi bi-life-preserver"></i>
                    <span><strong>Hubungi admin</strong><small>Satu ruang bantuan aktif per akun</small></span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            <?php endif;?>

            <div class="chat-conversation-list">
                <?php if(!$conversations):?>
                    <div class="chat-empty-list">
                        <i class="bi bi-chat-dots"></i>
                        <strong>Belum ada percakapan</strong>
                        <span>Chat seller dari halaman produk atau buat pengaduan ke admin.</span>
                    </div>
                <?php endif;?>

                <?php foreach($conversations as $c):?>
                    <a data-no-preloader="1"
                       class="chat-conversation-item <?=$selected&&(int)$selected['id']===(int)$c['id']?'is-active':''?>"
                       href="<?=e(page_url('chat',['id'=>$c['id']]))?>">
                        <span class="chat-avatar <?=$c['type']==='support'?'is-support':''?>">
                            <i class="bi <?=$c['type']==='support'?'bi-shield-check':'bi-person'?>"></i>
                        </span>
                        <span class="chat-conversation-copy">
                            <strong><?=e((string)$c['counterpart_name'])?></strong>
                            <small><?=$c['type']==='support'?e((string)($c['subject']?:'Pengaduan')):e((string)($c['product_name']?:'Chat produk'))?></small>
                            <span><?=e(mb_strimwidth((string)($c['last_message']??'Belum ada pesan'),0,60,'…'))?></span>
                        </span>
                    </a>
                <?php endforeach;?>
            </div>
        </aside>

        <section class="chat-main glass-card">
            <?php if(in_array($role,['customer','seller'],true)&&$newMode==='support'):?>
                <div class="chat-empty-state chat-compose-state">
                    <a data-no-preloader="1" class="chat-mobile-back" href="<?=e(page_url('chat'))?>">
                        <i class="bi bi-arrow-left"></i> Daftar chat
                    </a>
                    <span class="chat-hero-icon"><i class="bi bi-life-preserver"></i></span>
                    <h3>Buat pengaduan ke admin.</h3>
                    <p>Subjek dan isi pengaduan akan tersimpan di percakapan.</p>

                    <form method="post" class="chat-support-form text-start">
                        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                        <input type="hidden" name="chat_action" value="start_support">

                        <label class="form-label">Subjek pengaduan</label>
                        <input class="form-control mb-3" name="subject" maxlength="160" required>

                        <label class="form-label">Kendala</label>
                        <textarea class="form-control" name="body" rows="6" maxlength="2000" required></textarea>

                        <div class="d-flex gap-2 flex-wrap mt-3">
                            <button class="btn btn-primary" type="submit">Kirim pengaduan <i class="bi bi-send ms-2"></i></button>
                            <a data-no-preloader="1" class="btn btn-ghost" href="<?=e(page_url('chat'))?>">Batal</a>
                        </div>
                    </form>
                </div>

            <?php elseif(!$selected):?>
                <div class="chat-empty-state">
                    <span class="chat-hero-icon"><i class="bi bi-chat-heart"></i></span>
                    <h3><?=$role==='admin'?'Pilih pengaduan.':'Pilih percakapan.'?></h3>
                    <p><?=$role==='admin'?'Klik salah satu pengaduan di inbox untuk melihat isi lengkapnya.':'Pilih chat seller atau gunakan Hubungi admin.'?></p>
                </div>

            <?php else:?>
                <div class="chat-main-head">
                    <div class="chat-main-person">
                        <a data-no-preloader="1" class="chat-mobile-back" href="<?=e(page_url('chat'))?>"><i class="bi bi-arrow-left"></i></a>
                        <span class="chat-avatar chat-avatar-lg <?=$selected['type']==='support'?'is-support':''?>">
                            <i class="bi <?=$selected['type']==='support'?'bi-shield-check':'bi-person'?>"></i>
                        </span>
                        <div>
                            <strong><?=e((string)($selected['counterpart_name']?:'Pengguna'))?></strong>
                            <small><?=$selected['type']==='support'?e((string)($selected['subject']?:'Pengaduan KosCycle')):e((string)($selected['product_name']?:'Percakapan marketplace'))?></small>
                        </div>
                    </div>

                    <?php if($selected['type']==='support'&&$selected['status']==='open'&&$role==='admin'):?>
                        <form method="post" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                            <input type="hidden" name="chat_action" value="close">
                            <input type="hidden" name="conversation_id" value="<?=(int)$selected['id']?>">
                            <button class="btn btn-ghost btn-sm" type="submit">Tutup</button>
                        </form>
                    <?php endif;?>
                </div>

                <div class="chat-product-context">
                    <i class="bi <?=$selected['type']==='support'?'bi-life-preserver':'bi-box-seam'?>"></i>
                    <div>
                        <small><?=$selected['type']==='support'?'Isi pengaduan awal':'Produk yang dibahas'?></small>
                        <strong><?=e((string)($selected['type']==='support'?($selected['subject']?:'Bantuan KosCycle'):($selected['product_name']?:'Produk')))?></strong>
                        <?php if($selected['type']==='support'&&!empty($selected['first_message'])):?>
                            <p><?=nl2br(e((string)$selected['first_message']))?></p>
                        <?php endif;?>
                    </div>
                </div>

                <div class="chat-messages" id="chat-messages"
                     data-conversation-id="<?=(int)$selected['id']?>"
                     data-current-user="<?=$userId?>">
                    <?php foreach($messages as $m):?>
                        <?php $isMe=(int)$m['sender_id']===$userId;$read=$isMe&&$m['read_at']!==null;?>
                        <div class="chat-message-row <?=$isMe?'is-me':'is-them'?>" data-message-id="<?=(int)$m['id']?>">
                            <div class="chat-message-bubble">
                                <?php if(!$isMe):?><small class="chat-sender-label"><?=e((string)$m['sender_name'])?></small><?php endif;?>
                                <div><?=nl2br(e((string)$m['body']))?></div>
                                <div class="chat-message-meta">
                                    <time><?=e(date('H:i',strtotime((string)$m['created_at'])))?></time>
                                    <?php if($isMe):?>
                                        <span class="chat-read-receipt <?=$read?'is-read':'is-sent'?>" data-chat-receipt="1" title="<?=$read?'Sudah dibaca':'Terkirim'?>">
                                            <i class="bi <?=$read?'bi-check2-all':'bi-check2'?>"></i>
                                        </span>
                                    <?php endif;?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;?>
                </div>

                <?php if($selected['status']==='open'):?>
                    <form method="post" class="chat-composer" id="chat-composer">
                        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
                        <input type="hidden" name="chat_action" value="send">
                        <input type="hidden" name="conversation_id" value="<?=(int)$selected['id']?>">
                        <textarea class="form-control" name="body" rows="2" maxlength="2000" placeholder="Tulis pesan… (Enter untuk kirim)" required></textarea>
                        <button class="btn btn-primary chat-send-button" type="submit" aria-label="Kirim pesan"><i class="bi bi-send"></i></button>
                    </form>
                    <div class="chat-ajax-status" id="chat-ajax-status" aria-live="polite"></div>
                <?php else:?>
                    <div class="chat-closed-note"><i class="bi bi-lock me-2"></i>Pengaduan ini sudah ditutup oleh admin.</div>
                <?php endif;?>
            <?php endif;?>
        </section>
    </div>
</div>
</section>

<script>
(()=>{
    const box=document.getElementById('chat-messages');
    const composer=document.getElementById('chat-composer');
    const status=document.getElementById('chat-ajax-status');
    if(!box)return;

    const conversationId=box.dataset.conversationId;
    const currentUser=Number(box.dataset.currentUser||0);
    let lastId=0,polling=false,idleRounds=0;

    box.querySelectorAll('[data-message-id]').forEach(el=>{
        lastId=Math.max(lastId,Number(el.dataset.messageId||0));
    });

    const nearBottom=()=>box.scrollHeight-box.scrollTop-box.clientHeight<120;
    const scrollBottom=()=>{box.scrollTop=box.scrollHeight;};
    const escapeHtml=value=>String(value??'').replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
    const formatTime=raw=>{
        const d=new Date(String(raw||'').replace(' ','T'));
        return Number.isNaN(d.getTime())?'':d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
    };

    const appendMessage=message=>{
        const id=Number(message.id||0);
        if(!id||box.querySelector(`[data-message-id="${id}"]`))return false;

        const isMe=Number(message.sender_id)===currentUser;
        const row=document.createElement('div');
        row.className='chat-message-row '+(isMe?'is-me':'is-them');
        row.dataset.messageId=String(id);

        const sender=!isMe?`<small class="chat-sender-label">${escapeHtml(message.sender_name||'Pengguna')}</small>`:'';
        const receipt=isMe
            ? `<span class="chat-read-receipt ${message.read_at?'is-read':'is-sent'}" data-chat-receipt="1"><i class="bi ${message.read_at?'bi-check2-all':'bi-check2'}"></i></span>`
            : '';

        row.innerHTML=`<div class="chat-message-bubble">${sender}<div>${escapeHtml(message.body||'').replace(/\n/g,'<br>')}</div><div class="chat-message-meta"><time>${formatTime(message.created_at)}</time>${receipt}</div></div>`;
        box.appendChild(row);
        lastId=Math.max(lastId,id);
        return true;
    };

    const updateReceipts=readUpto=>{
        const max=Number(readUpto||0);
        box.querySelectorAll('.chat-message-row.is-me[data-message-id]').forEach(row=>{
            if(Number(row.dataset.messageId||0)>max)return;
            const receipt=row.querySelector('[data-chat-receipt]');
            if(!receipt)return;
            receipt.classList.remove('is-sent');
            receipt.classList.add('is-read');
            receipt.title='Sudah dibaca';
            const icon=receipt.querySelector('i');
            if(icon){
                icon.classList.remove('bi-check2');
                icon.classList.add('bi-check2-all');
            }
        });
    };

    const requestJson=async(url,options={})=>{
        const controller=new AbortController();
        const timeout=setTimeout(()=>controller.abort(),7000);
        try{
            const response=await fetch(url,{...options,signal:controller.signal,cache:'no-store'});
            const data=await response.json();
            if(!response.ok||!data.ok)throw new Error(data.message||'Permintaan chat gagal.');
            return data;
        }finally{
            clearTimeout(timeout);
        }
    };

    const poll=async()=>{
        if(polling)return;
        polling=true;
        let added=false;

        try{
            const stick=nearBottom();
            const url=new URL(window.location.href);
            url.searchParams.set('chat_action','messages');
            url.searchParams.set('id',conversationId);
            url.searchParams.set('after',String(lastId));

            const data=await requestJson(url.toString(),{headers:{'X-Requested-With':'XMLHttpRequest'}});
            (data.messages||[]).forEach(m=>{if(appendMessage(m))added=true;});
            updateReceipts(data.read_upto||0);

            if(added){
                idleRounds=0;
                if(stick)scrollBottom();
            }else{
                idleRounds++;
            }
        }catch(_){
            idleRounds=Math.min(idleRounds+1,10);
        }finally{
            polling=false;
            const delay=document.hidden?8000:(idleRounds>8?5000:2000);
            setTimeout(poll,delay);
        }
    };

    if(composer){
        const textarea=composer.querySelector('textarea[name="body"]');

        composer.addEventListener('submit',async event=>{
            event.preventDefault();
            const button=composer.querySelector('button[type="submit"]');
            if(!textarea||!button||!textarea.value.trim())return;

            button.disabled=true;
            if(status)status.textContent='';

            try{
                const data=await requestJson(window.location.href,{
                    method:'POST',
                    body:new FormData(composer),
                    headers:{'X-Requested-With':'XMLHttpRequest'}
                });

                if(data.message)appendMessage(data.message);
                textarea.value='';
                updateReceipts(data.read_upto||0);
                scrollBottom();
                idleRounds=0;
                textarea.focus();
            }catch(error){
                if(status)status.textContent=error.message||'Pesan gagal dikirim.';
            }finally{
                button.disabled=false;
            }
        });

        textarea?.addEventListener('keydown',event=>{
            if(event.key==='Enter'&&!event.shiftKey){
                event.preventDefault();
                composer.requestSubmit();
            }
        });
    }

    scrollBottom();
    poll();
})();
</script>

<?php require __DIR__.'/../includes/footer.php';?>
