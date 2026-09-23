<?php
require_once __DIR__ . '/../includes/authorization.php';
require_roles(['customer', 'seller', 'admin']);
require_once __DIR__ . '/../includes/Repositories/ChatRepository.php';

$chat = new ChatRepository();
$user = current_user();
$userId = (int) $user['id'];
$role = (string) $user['role'];
$action = (string) ($_GET['chat_action'] ?? '');
$conversationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$newMode = (string) ($_GET['new'] ?? '');
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

$findOpenSupportId = static function (int $uid, string $userRole): int {
    if (!in_array($userRole, ['customer', 'seller'], true)) return 0;
    try {
        $column = $userRole === 'customer' ? 'customer_id' : 'seller_id';
        $stmt = db()->prepare("SELECT id FROM chat_conversations
            WHERE type='support' AND status='open' AND {$column}=?
            ORDER BY COALESCE(last_message_at,updated_at,created_at) DESC,id DESC LIMIT 1");
        $stmt->execute([$uid]);
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable) {
        return 0;
    }
};

if ($newMode === 'support' && in_array($role, ['customer', 'seller'], true)) {
    $existingSupportId = $findOpenSupportId($userId, $role);
    if ($existingSupportId > 0) redirect(page_url('chat', ['id' => $existingSupportId]));
}

if ($action === 'messages') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $after = filter_input(INPUT_GET, 'after', FILTER_VALIDATE_INT) ?: 0;
        $conversation = $chat->find($conversationId, $userId, $role);
        if (!$conversation) {
            http_response_code(404);
            echo json_encode(['ok'=>false,'message'=>'Percakapan tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $chat->markRead($conversationId, $userId, $role);
        echo json_encode([
            'ok'=>true,
            'conversation'=>$conversation,
            'messages'=>$chat->messages($conversationId,$userId,$role,$after),
            'read_upto'=>$chat->readUpto($conversationId,$userId,$role),
            'unread'=>$chat->unreadCount($userId,$role),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (is_post()) {
    $redirectId = $conversationId;
    if (!verify_csrf()) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(419);
            echo json_encode(['ok'=>false,'message'=>'Sesi formulir tidak valid. Muat ulang halaman.'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        flash('error','Sesi formulir tidak valid. Muat ulang halaman.');
        redirect(page_url('chat',$redirectId?['id'=>$redirectId]:[]));
    }

    $formAction=(string)($_POST['chat_action']??'');
    try {
        if ($formAction === 'start_marketplace') {
            if ($role !== 'customer') throw new RuntimeException('Aksi tidak diizinkan.');
            $productId=filter_input(INPUT_POST,'product_id',FILTER_VALIDATE_INT);
            if(!$productId) throw new RuntimeException('Produk tidak valid.');
            $redirectId=$chat->startMarketplace($userId,$role,(int)$productId);
            redirect(page_url('chat',['id'=>$redirectId]));
        }

        if ($formAction === 'start_support') {
            $subject=trim((string)($_POST['subject']??''));
            $body=trim((string)($_POST['body']??''));
            $existingSupportId=$findOpenSupportId($userId,$role);
            if($existingSupportId>0){
                $redirectId=$existingSupportId;
                $chat->sendMessage($redirectId,$userId,$role,$body);
            }else{
                $redirectId=$chat->startSupport($userId,$role,$subject,$body);
            }
            if($isAjax){
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok'=>true,'conversation_id'=>$redirectId],JSON_UNESCAPED_UNICODE);
                exit;
            }
            redirect(page_url('chat',['id'=>$redirectId]));
        }

        if ($formAction === 'send') {
            $id=filter_input(INPUT_POST,'conversation_id',FILTER_VALIDATE_INT);
            if(!$id) throw new RuntimeException('Percakapan tidak valid.');
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

        if ($formAction === 'close') {
            $id=filter_input(INPUT_POST,'conversation_id',FILTER_VALIDATE_INT);
            if(!$id) throw new RuntimeException('Percakapan tidak valid.');
            $chat->closeConversation((int)$id,$userId,$role);
            flash('success','Pengaduan ditutup.');
            redirect(page_url('chat',['id'=>(int)$id]));
        }
    } catch (Throwable $e) {
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

$chatLoadError=null;
try {
    $conversations=$chat->listForUser($userId,$role);
    $selected=$conversationId?$chat->find($conversationId,$userId,$role):null;
    if($selected)$chat->markRead($conversationId,$userId,$role);
    $messages=$selected?$chat->messages($conversationId,$userId,$role):[];
    $unread=$chat->unreadCount($userId,$role);
} catch (Throwable $e) {
    $conversations=[];$selected=null;$messages=[];$unread=0;
    $chatLoadError='Chat belum dapat dimuat. Pastikan database.mysql.chat.sql sudah di-import.';
}

$pageTitle=$role==='admin'?'Pusat Pengaduan':'Chat';
require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/chat.css">
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/chat-receipts-polish.css">

<section class="section-padding page-section chat-page">
    <div class="container">
        <div class="page-toolbar"><?=back_link(dashboard_page_for_role($role),'Kembali ke dashboard')?></div>
        <div class="section-heading mb-4">
            <span class="eyebrow"><i class="bi bi-chat-dots me-1"></i><?=$role==='admin'?'Support':'Pesan'?></span>
            <h2><?=$role==='admin'?'Pusat <em>pengaduan.</em>':'Live chat <em>KosCycle.</em>'?></h2>
            <p class="text-muted mb-0">Pesan diperbarui otomatis tanpa refresh halaman.</p>
        </div>

        <?php if($chatLoadError):?><div class="alert alert-danger glass-alert mb-4"><?=e($chatLoadError)?></div><?php endif;?>
        <?php if($message=flash('success')):?><div class="alert alert-success glass-alert mb-4"><?=e($message)?></div><?php endif;?>
        <?php if($message=flash('error')):?><div class="alert alert-danger glass-alert mb-4"><?=e($message)?></div><?php endif;?>

        <div class="chat-shell <?=$selected?'has-selection':''?> <?=$newMode==='support'?'is-compose':''?>">
            <aside class="chat-sidebar glass-card">
                <div class="chat-sidebar-head">
                    <div><span class="eyebrow">Inbox</span><h3 class="chat-list-title"><?=$role==='admin'?'Pengaduan pengguna':'Percakapanmu'?></h3></div>
                    <?php if($unread>0):?><span class="chat-count-badge"><?=$unread?></span><?php endif;?>
                </div>

                <?php if(in_array($role,['customer','seller'],true)):?>
                    <a class="chat-support-button" data-no-preloader="1" href="<?=e(page_url('chat',['new'=>'support']))?>"><i class="bi bi-life-preserver"></i><span><strong>Hubungi admin</strong><small>Buka ruang bantuan tanpa loading berulang</small></span><i class="bi bi-arrow-right"></i></a>
                <?php endif;?>

                <div class="chat-conversation-list">
                    <?php if(!$conversations):?><div class="chat-empty-list"><i class="bi bi-chat-dots"></i><strong>Belum ada percakapan</strong><span>Chat seller dari halaman produk atau buat pengaduan ke admin.</span></div><?php endif;?>
                    <?php foreach($conversations as $c):?>
                        <a data-no-preloader="1" class="chat-conversation-item <?=$selected&&(int)$selected['id']===(int)$c['id']?'is-active':''?>" href="<?=e(page_url('chat',['id'=>$c['id']]))?>">
                            <span class="chat-avatar <?=$c['type']==='support'?'is-support':''?>"><i class="bi <?=$c['type']==='support'?'bi-shield-check':'bi-person'?>"></i></span>
                            <span class="chat-conversation-copy"><strong><?=e((string)$c['counterpart_name'])?></strong><small><?=$c['type']==='support'?e((string)($c['subject']?:'Pengaduan')):e((string)($c['product_name']?:'Chat produk'))?></small><span><?=e(mb_strimwidth((string)($c['last_message']??'Belum ada pesan'),0,60,'…'))?></span></span>
                            <?php if((int)$c['unread_count']>0):?><span class="chat-unread-dot"><?=(int)$c['unread_count']?></span><?php endif;?>
                        </a>
                    <?php endforeach;?>
                </div>
            </aside>

            <section class="chat-main glass-card">
                <?php if(in_array($role,['customer','seller'],true)&&$newMode==='support'):?>
                    <div class="chat-empty-state chat-compose-state">
                        <a data-no-preloader="1" class="chat-mobile-back" href="<?=e(page_url('chat'))?>"><i class="bi bi-arrow-left"></i> Daftar chat</a>
                        <span class="chat-hero-icon"><i class="bi bi-life-preserver"></i></span><h3>Buat pengaduan ke admin.</h3><p>Jelaskan masalahnya agar admin bisa membantu dengan cepat.</p>
                        <form method="post" class="chat-support-form text-start" id="support-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="chat_action" value="start_support"><label class="form-label">Subjek pengaduan</label><input class="form-control mb-3" name="subject" maxlength="160" placeholder="Contoh: Pembayaran pesanan bermasalah" required><label class="form-label">Kendala</label><textarea class="form-control" name="body" rows="6" maxlength="2000" placeholder="Ceritakan kendalanya di sini…" required><div class="d-flex gap-2 flex-wrap mt-3"><button class="btn btn-primary" type="submit">Kirim pengaduan <i class="bi bi-send ms-2"></i></button><a data-no-preloader="1" class="btn btn-ghost" href="<?=e(page_url('chat'))?>">Batal</a></div></form>
                    </div>
                <?php elseif(!$selected):?>
                    <div class="chat-empty-state"><span class="chat-hero-icon"><i class="bi bi-chat-heart"></i></span><h3><?=$role==='admin'?'Pilih pengaduan.':'Pilih percakapan.'?></h3><p><?=$role==='admin'?'Daftar pengaduan tersedia di sisi kiri.':'Pilih chat seller atau gunakan Hubungi admin.'?></p></div>
                <?php else:?>
                    <div class="chat-main-head">
                        <div class="chat-main-person">
                            <a data-no-preloader="1" class="chat-mobile-back" href="<?=e(page_url('chat'))?>"><i class="bi bi-arrow-left"></i></a>
                            <span class="chat-avatar chat-avatar-lg <?=$selected['type']==='support'?'is-support':''?>"><i class="bi <?=$selected['type']==='support'?'bi-shield-check':'bi-person'?>"></i></span>
                            <div><strong><?=e((string)($selected['counterpart_name']?:'Pengguna'))?></strong><small><?=$selected['type']==='support'?e((string)($selected['subject']?:'Pengaduan KosCycle')):e((string)($selected['product_name']?:'Percakapan marketplace'))?></small></div>
                        </div>
                        <?php if($selected['type']==='support'&&$selected['status']==='open'&&$role==='admin'):?><form method="post" class="m-0"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="chat_action" value="close"><input type="hidden" name="conversation_id" value="<?=(int)$selected['id']?>"><button class="btn btn-ghost btn-sm" type="submit">Tutup</button></form><?php endif;?>
                    </div>

                    <div class="chat-product-context"><i class="bi <?=$selected['type']==='support'?'bi-life-preserver':'bi-box-seam'?>"></i><div><small><?=$selected['type']==='support'?'Topik pengaduan':'Produk yang dibahas'?></small><strong><?=e((string)($selected['type']==='support'?($selected['subject']?:'Bantuan KosCycle'):($selected['product_name']?:'Produk')))?></strong></div></div>

                    <div class="chat-messages" id="chat-messages" data-conversation-id="<?=(int)$selected['id']?>" data-current-user="<?=$userId?>">
                        <?php foreach($messages as $m):?>
                            <?php $isMe=(int)$m['sender_id']===$userId;$read=$isMe&&$m['read_at']!==null;?>
                            <div class="chat-message-row <?=$isMe?'is-me':'is-them'?>" data-message-id="<?=(int)$m['id']?>"><div class="chat-message-bubble"><?php if(!$isMe):?><small class="chat-sender-label"><?=e((string)$m['sender_name'])?></small><?php endif;?><div><?=nl2br(e((string)$m['body']))?></div><div class="chat-message-meta"><time><?=e(date('H:i',strtotime((string)$m['created_at'])))?></time><span class="chat-read-receipt <?=$read?'is-read':'is-sent'?> <?=$isMe?'':'is-incoming'?>" data-chat-receipt="1"><i class="bi <?=$read?'bi-check2-all':'bi-check2'?>"></i></span></div></div></div>
                        <?php endforeach;?>
                    </div>

                    <?php if($selected['status']==='open'):?>
                        <form method="post" class="chat-composer" id="chat-composer"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="chat_action" value="send"><input type="hidden" name="conversation_id" value="<?=(int)$selected['id']?>"><textarea class="form-control" name="body" rows="2" maxlength="2000" placeholder="Tulis pesan… (Enter untuk kirim)" required></textarea><button class="btn btn-primary chat-send-button" type="submit" aria-label="Kirim pesan"><i class="bi bi-send"></i></button></form><div class="chat-ajax-status" id="chat-ajax-status" aria-live="polite"></div>
                    <?php else:?><div class="chat-closed-note"><i class="bi bi-lock me-2"></i>Pengaduan ini sudah ditutup oleh admin.</div><?php endif;?>
                <?php endif;?>
            </section>
        </div>
    </div>
</section>

<script>
(() => {
    const box=document.getElementById('chat-messages');
    const composer=document.getElementById('chat-composer');
    const status=document.getElementById('chat-ajax-status');
    if(!box)return;

    const conversationId=box.dataset.conversationId;
    const currentUser=Number(box.dataset.currentUser||0);
    let lastId=0,polling=false;
    box.querySelectorAll('[data-message-id]').forEach(el=>{lastId=Math.max(lastId,Number(el.dataset.messageId||0));});

    const nearBottom=()=>box.scrollHeight-box.scrollTop-box.clientHeight<120;
    const scrollBottom=()=>{box.scrollTop=box.scrollHeight;};
    const formatTime=raw=>{const d=new Date(String(raw||'').replace(' ','T'));return Number.isNaN(d.getTime())?'':d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});};
    const escapeHtml=value=>String(value??'').replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));

    const appendMessage=message=>{
        const id=Number(message.id||0);if(!id||box.querySelector(`[data-message-id="${id}"]`))return false;
        const isMe=Number(message.sender_id)===currentUser;
        const row=document.createElement('div');row.className='chat-message-row '+(isMe?'is-me':'is-them');row.dataset.messageId=String(id);
        const sender=!isMe?`<small class="chat-sender-label">${escapeHtml(message.sender_name||'Pengguna')}</small>`:'';
        row.innerHTML=`<div class="chat-message-bubble">${sender}<div>${escapeHtml(message.body||'').replace(/\n/g,'<br>')}</div><div class="chat-message-meta"><time>${formatTime(message.created_at)}</time><span class="chat-read-receipt ${isMe&&message.read_at?'is-read':'is-sent'} ${isMe?'':'is-incoming'}" data-chat-receipt="1"><i class="bi ${isMe&&message.read_at?'bi-check2-all':'bi-check2'}"></i></span></div></div>`;
        box.appendChild(row);lastId=Math.max(lastId,id);return true;
    };

    const updateReceipts=readUpto=>{
        const max=Number(readUpto||0);
        box.querySelectorAll('.chat-message-row.is-me[data-message-id]').forEach(row=>{
            if(Number(row.dataset.messageId||0)>max)return;
            const receipt=row.querySelector('[data-chat-receipt]');if(!receipt)return;
            receipt.classList.remove('is-sent');receipt.classList.add('is-read');
            const icon=receipt.querySelector('i');if(icon){icon.classList.remove('bi-check2');icon.classList.add('bi-check2-all');}
        });
    };

    const requestJson=async(url,options={})=>{
        const controller=new AbortController();
        const timeout=setTimeout(()=>controller.abort(),8000);
        try{
            const response=await fetch(url,{...options,signal:controller.signal,cache:'no-store'});
            const data=await response.json();
            if(!response.ok||!data.ok)throw new Error(data.message||'Permintaan chat gagal.');
            return data;
        }finally{clearTimeout(timeout);}
    };

    const poll=async()=>{
        if(polling)return;polling=true;
        try{
            const stick=nearBottom();
            const url=new URL(window.location.href);url.searchParams.set('chat_action','messages');url.searchParams.set('id',conversationId);url.searchParams.set('after',String(lastId));
            const data=await requestJson(url.toString(),{headers:{'X-Requested-With':'XMLHttpRequest'}});
            let added=false;(data.messages||[]).forEach(m=>{if(appendMessage(m))added=true;});updateReceipts(data.read_upto||0);if(added&&stick)scrollBottom();
        }catch(_){/* koneksi berikutnya akan mencoba lagi */}
        finally{polling=false;setTimeout(poll,document.hidden?5000:1200);}
    };

    if(composer){
        const textarea=composer.querySelector('textarea[name="body"]');
        composer.addEventListener('submit',async event=>{
            event.preventDefault();const button=composer.querySelector('button[type="submit"]');if(!textarea||!button)return;
            if(!textarea.value.trim())return;
            button.disabled=true;if(status)status.textContent='';
            try{
                const data=await requestJson(window.location.href,{method:'POST',body:new FormData(composer),headers:{'X-Requested-With':'XMLHttpRequest'}});
                if(data.message)appendMessage(data.message);textarea.value='';updateReceipts(data.read_upto||0);scrollBottom();textarea.focus();
            }catch(error){if(status)status.textContent=error.message||'Pesan gagal dikirim.';}
            finally{button.disabled=false;}
        });
        textarea?.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();composer.requestSubmit();}});
    }

    scrollBottom();poll();
})();
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
