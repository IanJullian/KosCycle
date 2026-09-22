<?php
require_once __DIR__ . '/../includes/authorization.php';
require_roles(['customer', 'seller', 'admin']);
require_once __DIR__ . '/../includes/Repositories/ChatRepository.php';

$chat = new ChatRepository();
$user = current_user();
$userId = (int) $user['id'];
$role = (string) $user['role'];

$action = $_GET['chat_action'] ?? '';
$conversationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$newMode = $_GET['new'] ?? '';

if ($action === 'messages') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $after = filter_input(INPUT_GET, 'after', FILTER_VALIDATE_INT) ?: 0;
        $conversation = $chat->find($conversationId, $userId, $role);
        if (!$conversation) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Percakapan tidak ditemukan.']);
            exit;
        }

        $messages = $chat->messages($conversationId, $userId, $role, $after);
        $chat->markRead($conversationId, $userId, $role);

        echo json_encode([
            'ok' => true,
            'conversation' => $conversation,
            'messages' => $messages,
            'unread' => $chat->unreadCount($userId, $role),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (is_post()) {
    $redirectId = $conversationId;

    if (!verify_csrf()) {
        flash('error', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        redirect(page_url('chat', $redirectId ? ['id' => $redirectId] : []));
    }

    $formAction = (string) ($_POST['chat_action'] ?? '');

    try {
        if ($formAction === 'start_marketplace') {
            if ($role !== 'customer') {
                throw new RuntimeException('Aksi tidak diizinkan.');
            }
            $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
            if (!$productId) {
                throw new RuntimeException('Produk tidak valid.');
            }

            $redirectId = $chat->startMarketplace($userId, $role, (int) $productId);
            redirect(page_url('chat', ['id' => $redirectId]));
        }

        if ($formAction === 'start_support') {
            $redirectId = $chat->startSupport(
                $userId,
                $role,
                (string) ($_POST['subject'] ?? ''),
                (string) ($_POST['body'] ?? '')
            );
            flash('success', 'Pengaduan berhasil dibuat. Admin dapat membalas dari pusat pengaduan.');
            redirect(page_url('chat', ['id' => $redirectId]));
        }

        if ($formAction === 'send') {
            $id = filter_input(INPUT_POST, 'conversation_id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('Percakapan tidak valid.');
            }

            $chat->sendMessage((int) $id, $userId, $role, (string) ($_POST['body'] ?? ''));
            redirect(page_url('chat', ['id' => (int) $id]));
        }

        if ($formAction === 'close') {
            $id = filter_input(INPUT_POST, 'conversation_id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('Percakapan tidak valid.');
            }

            $chat->closeConversation((int) $id, $userId, $role);
            flash('success', 'Pengaduan ditutup.');
            redirect(page_url('chat', ['id' => (int) $id]));
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect(page_url('chat', $redirectId ? ['id' => $redirectId] : []));
    }
}

$conversations = $chat->listForUser($userId, $role);
$selected = $conversationId ? $chat->find($conversationId, $userId, $role) : null;
if ($selected) {
    $chat->markRead($conversationId, $userId, $role);
}
$messages = $selected ? $chat->messages($conversationId, $userId, $role) : [];
$unread = $chat->unreadCount($userId, $role);

$pageTitle = $role === 'admin' ? 'Pusat Pengaduan' : 'Chat';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/chat.css">

<section class="section-padding page-section chat-page">
    <div class="container">
        <div class="page-toolbar"><?= back_link(dashboard_page_for_role($role), 'Kembali ke dashboard') ?></div>

        <div class="section-heading mb-4">
            <span class="eyebrow"><i class="bi bi-chat-dots me-1"></i><?= $role === 'admin' ? 'Support' : 'Pesan' ?></span>
            <h2><?= $role === 'admin' ? 'Pusat <em>pengaduan.</em>' : 'Chat <em>KosCycle.</em>' ?></h2>
            <p class="text-muted mb-0">
                <?= $role === 'admin'
                    ? 'Tangani pengaduan customer dan seller dari satu ruang kerja.'
                    : 'Tanyakan produk langsung ke seller atau hubungi admin saat ada kendala.' ?>
            </p>
        </div>

        <?php if ($message = flash('success')): ?>
            <div class="alert alert-success glass-alert mb-4"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($message = flash('error')): ?>
            <div class="alert alert-danger glass-alert mb-4"><?= e($message) ?></div>
        <?php endif; ?>

        <div class="chat-shell">
            <aside class="chat-sidebar glass-card">
                <div class="chat-sidebar-head">
                    <div>
                        <span class="eyebrow">Inbox</span>
                        <h3 class="chat-list-title"><?= $role === 'admin' ? 'Pengaduan pengguna' : 'Percakapanmu' ?></h3>
                    </div>
                    <?php if ($unread > 0): ?><span class="chat-count-badge"><?= $unread ?></span><?php endif; ?>
                </div>

                <?php if (in_array($role, ['customer', 'seller'], true)): ?>
                    <a class="chat-support-button" href="<?= e(page_url('chat', ['new' => 'support'])) ?>">
                        <i class="bi bi-life-preserver"></i>
                        <span><strong>Hubungi admin</strong><small>Pengaduan, bantuan, atau kendala</small></span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                <?php endif; ?>

                <div class="chat-conversation-list">
                    <?php if (!$conversations): ?>
                        <div class="chat-empty-list">
                            <i class="bi bi-chat-dots"></i>
                            <strong>Belum ada percakapan</strong>
                            <span>Chat seller dari halaman produk atau buat pengaduan ke admin.</span>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($conversations as $c): ?>
                        <a class="chat-conversation-item <?= $selected && (int) $selected['id'] === (int) $c['id'] ? 'is-active' : '' ?>"
                           href="<?= e(page_url('chat', ['id' => $c['id']])) ?>">
                            <span class="chat-avatar <?= $c['type'] === 'support' ? 'is-support' : '' ?>">
                                <i class="bi <?= $c['type'] === 'support' ? 'bi-shield-check' : 'bi-person' ?>"></i>
                            </span>
                            <span class="chat-conversation-copy">
                                <strong><?= e((string) $c['counterpart_name']) ?></strong>
                                <small>
                                    <?= $c['type'] === 'support' ? e((string) ($c['subject'] ?: 'Pengaduan')) : e((string) ($c['product_name'] ?: 'Chat produk')) ?>
                                </small>
                                <span><?= e(mb_strimwidth((string) ($c['last_message'] ?? 'Belum ada pesan'), 0, 60, '…')) ?></span>
                            </span>
                            <?php if ((int) $c['unread_count'] > 0): ?>
                                <span class="chat-unread-dot"><?= (int) $c['unread_count'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <section class="chat-main glass-card">
                <?php if (in_array($role, ['customer', 'seller'], true) && $newMode === 'support'): ?>
                    <div class="chat-empty-state chat-compose-state">
                        <span class="chat-hero-icon"><i class="bi bi-life-preserver"></i></span>
                        <h3>Buat pengaduan ke admin.</h3>
                        <p>Jelaskan masalahnya sedetail mungkin agar admin bisa menindaklanjuti dengan cepat.</p>

                        <form method="post" class="chat-support-form text-start">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="chat_action" value="start_support">

                            <label class="form-label">Subjek pengaduan</label>
                            <input class="form-control mb-3" name="subject" maxlength="160" placeholder="Contoh: Pesanan #1024 belum diproses" required>

                            <label class="form-label">Kendala</label>
                            <textarea class="form-control" name="body" rows="6" maxlength="2000" placeholder="Ceritakan kendalanya di sini…" required></textarea>

                            <div class="d-flex gap-2 flex-wrap mt-3">
                                <button class="btn btn-primary" type="submit">
                                    Kirim pengaduan <i class="bi bi-send ms-2"></i>
                                </button>
                                <a class="btn btn-ghost" href="<?= e(page_url('chat')) ?>">Batal</a>
                            </div>
                        </form>
                    </div>
                <?php elseif (!$selected): ?>
                    <div class="chat-empty-state">
                        <span class="chat-hero-icon"><i class="bi bi-chat-heart"></i></span>
                        <h3><?= $role === 'admin' ? 'Pilih pengaduan.' : 'Pilih percakapan.' ?></h3>
                        <p>
                            <?= $role === 'admin'
                                ? 'Daftar pengaduan pengguna tersedia di sisi kiri.'
                                : 'Chat seller dimulai dari detail produk, sedangkan pengaduan admin tersedia di tombol Hubungi admin.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="chat-main-head">
                        <div class="chat-main-person">
                            <span class="chat-avatar chat-avatar-lg <?= $selected['type'] === 'support' ? 'is-support' : '' ?>">
                                <i class="bi <?= $selected['type'] === 'support' ? 'bi-shield-check' : 'bi-person' ?>"></i>
                            </span>
                            <div>
                                <strong><?= e((string) ($selected['counterpart_name'] ?: 'Pengguna')) ?></strong>
                                <small>
                                    <?= $selected['type'] === 'support'
                                        ? e((string) ($selected['subject'] ?: 'Pengaduan KosCycle'))
                                        : e((string) ($selected['product_name'] ?: 'Percakapan marketplace')) ?>
                                </small>
                            </div>
                        </div>

                        <?php if ($selected['type'] === 'support' && $selected['status'] === 'open' && $role === 'admin'): ?>
                            <form method="post" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="chat_action" value="close">
                                <input type="hidden" name="conversation_id" value="<?= (int) $selected['id'] ?>">
                                <button class="btn btn-ghost btn-sm" type="submit">
                                    <i class="bi bi-check2-circle me-1"></i>Tutup pengaduan
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="chat-product-context">
                        <i class="bi <?= $selected['type'] === 'support' ? 'bi-life-preserver' : 'bi-box-seam' ?>"></i>
                        <div>
                            <small><?= $selected['type'] === 'support' ? 'Topik pengaduan' : 'Produk yang dibahas' ?></small>
                            <strong><?= e((string) ($selected['type'] === 'support' ? ($selected['subject'] ?: 'Bantuan KosCycle') : ($selected['product_name'] ?: 'Produk'))) ?></strong>
                        </div>
                    </div>

                    <div class="chat-messages" id="chat-messages" data-conversation-id="<?= (int) $selected['id'] ?>" data-current-user="<?= $userId ?>">
                        <?php foreach ($messages as $m): ?>
                            <div class="chat-message-row <?= (int) $m['sender_id'] === $userId ? 'is-me' : 'is-them' ?>" data-message-id="<?= (int) $m['id'] ?>">
                                <div class="chat-message-bubble">
                                    <?php if ((int) $m['sender_id'] !== $userId): ?>
                                        <small class="chat-sender-label"><?= e((string) $m['sender_name']) ?></small>
                                    <?php endif; ?>
                                    <div><?= nl2br(e((string) $m['body'])) ?></div>
                                    <time><?= e(date('H:i', strtotime((string) $m['created_at']))) ?></time>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($selected['status'] === 'open'): ?>
                        <form method="post" class="chat-composer" id="chat-composer">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="chat_action" value="send">
                            <input type="hidden" name="conversation_id" value="<?= (int) $selected['id'] ?>">
                            <textarea class="form-control" name="body" rows="2" maxlength="2000" placeholder="Tulis pesan…" required></textarea>
                            <button class="btn btn-primary chat-send-button" type="submit" aria-label="Kirim pesan">
                                <i class="bi bi-send"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="chat-closed-note">
                            <i class="bi bi-lock me-2"></i>Pengaduan ini sudah ditutup oleh admin.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<script>
(() => {
    const box = document.getElementById('chat-messages');
    if (!box) return;

    const conversationId = box.dataset.conversationId;
    const currentUser = Number(box.dataset.currentUser || 0);
    let lastId = 0;

    box.querySelectorAll('[data-message-id]').forEach((el) => {
        lastId = Math.max(lastId, Number(el.dataset.messageId || 0));
    });

    const appendMessage = (message) => {
        const messageId = Number(message.id || 0);
        if (!messageId || box.querySelector(`[data-message-id="${messageId}"]`)) return;

        const row = document.createElement('div');
        row.className = 'chat-message-row ' + (Number(message.sender_id) === currentUser ? 'is-me' : 'is-them');
        row.dataset.messageId = String(messageId);

        const bubble = document.createElement('div');
        bubble.className = 'chat-message-bubble';

        if (Number(message.sender_id) !== currentUser) {
            const sender = document.createElement('small');
            sender.className = 'chat-sender-label';
            sender.textContent = message.sender_name || 'Pengguna';
            bubble.appendChild(sender);
        }

        const body = document.createElement('div');
        body.textContent = message.body || '';
        bubble.appendChild(body);

        const time = document.createElement('time');
        const raw = String(message.created_at || '').replace(' ', 'T');
        const date = new Date(raw);
        time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        bubble.appendChild(time);

        row.appendChild(bubble);
        box.appendChild(row);
        lastId = Math.max(lastId, messageId);
    };

    const scrollBottom = () => {
        box.scrollTop = box.scrollHeight;
    };

    scrollBottom();

    const poll = async () => {
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('chat_action', 'messages');
            url.searchParams.set('id', conversationId);
            url.searchParams.set('after', String(lastId));

            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });

            if (!response.ok) return;
            const data = await response.json();
            if (!data.ok) return;

            const incoming = data.messages || [];
            incoming.forEach(appendMessage);
            if (incoming.length) scrollBottom();
        } catch (_) {
            // Polling is best-effort; normal page interaction keeps working if it fails.
        }
    };

    window.setInterval(poll, 3000);
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
