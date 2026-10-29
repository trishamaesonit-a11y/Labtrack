<?php
// Expects $area and authenticated $u.
$uid = (int)$u['user_id'];
$messagesUrl = ($area === 'admin' ? 'admin' : 'user') . '/messages.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create_group') {
        $name = post('group_name');
        $members = array_values(array_unique(array_map('intval', $_POST['members'] ?? [])));
        $members = array_values(array_filter($members, fn($id) => $id > 0 && $id !== $uid));

        if ($name === '') {
            flash('Enter a group name.', 'err');
        } elseif (!$members) {
            flash('Select at least one other member.', 'err');
        } else {
            try {
                $gid = transaction(function () use ($name, $uid, $members) {
                    q('INSERT INTO group_chats (group_name,created_by) VALUES (?,?)', [$name, $uid]);
                    $newId = (int)db()->lastInsertId();
                    q('INSERT INTO group_members (group_id,user_id) VALUES (?,?)', [$newId, $uid]);
                    foreach ($members as $mid) {
                        if (val("SELECT COUNT(*) FROM users WHERE user_id=? AND status='active'", [$mid])) {
                            q('INSERT IGNORE INTO group_members (group_id,user_id) VALUES (?,?)', [$newId, $mid]);
                        }
                    }
                    return $newId;
                });
                flash('Group chat created.');
                redirect($messagesUrl . '?group=' . $gid);
            } catch (Throwable $ex) { flash('Could not create the group. Nothing was saved.', 'err'); }
        }
    } elseif ($action === 'send') {
        $body = post('body');
        $gid = (int)post('group_id');
        $to = (int)post('recipient_id');
        if ($body === '') {
            flash('Message cannot be empty.', 'err');
        } elseif ($gid && val('SELECT COUNT(*) FROM group_members WHERE group_id=? AND user_id=?', [$gid, $uid])) {
            q('INSERT INTO messages (sender_id,group_id,subject,body) VALUES (?,?,?,?)', [$uid, $gid, post('subject'), $body]);
            q("UPDATE user_conversations SET is_archived=0,is_deleted=0
               WHERE conversation_type='group' AND conversation_id=? AND user_id IN
               (SELECT user_id FROM group_members WHERE group_id=?)", [$gid, $gid]);
        } elseif ($to && $to !== $uid && val("SELECT COUNT(*) FROM users WHERE user_id=? AND status='active'", [$to])) {
            q('INSERT INTO messages (sender_id,recipient_id,subject,body) VALUES (?,?,?,?)', [$uid, $to, post('subject'), $body]);
            q("UPDATE user_conversations SET is_archived=0,is_deleted=0
               WHERE user_id=? AND conversation_type='direct' AND conversation_id=?", [$uid, $to]);
            q("UPDATE user_conversations SET is_archived=0,is_deleted=0
               WHERE user_id=? AND conversation_type='direct' AND conversation_id=?", [$to, $uid]);
        } else {
            flash('Choose a valid conversation.', 'err');
        }
        redirect($messagesUrl . ($gid ? '?group=' . $gid : ($to ? '?contact=' . $to : '')));
    } elseif (in_array($action, ['archive', 'unarchive', 'delete'], true)) {
        $type = post('conversation_type');
        $conversationId = (int)post('conversation_id');
        $view = post('view') === 'archived' ? 'archived' : 'inbox';
        $valid = false;

        if ($type === 'group' && $conversationId > 0) {
            $valid = (bool)val('SELECT COUNT(*) FROM group_members WHERE group_id=? AND user_id=?', [$conversationId, $uid]);
        } elseif ($type === 'direct' && $conversationId > 0 && $conversationId !== $uid) {
            $valid = (bool)val(
                'SELECT COUNT(*) FROM messages
                 WHERE group_id IS NULL AND
                 ((sender_id=? AND recipient_id=?) OR (sender_id=? AND recipient_id=?))',
                [$uid, $conversationId, $conversationId, $uid]
            );
        }

        if (!$valid) {
            flash('That conversation is not available.', 'err');
        } else {
            $archived = $action === 'archive' ? 1 : 0;
            $deleted = $action === 'delete' ? 1 : 0;
            q(
                'INSERT INTO user_conversations (user_id,conversation_type,conversation_id,is_archived,is_deleted)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE is_archived=VALUES(is_archived),is_deleted=VALUES(is_deleted)',
                [$uid, $type, $conversationId, $archived, $deleted]
            );
            flash($action === 'archive' ? 'Conversation archived.' : ($action === 'unarchive' ? 'Conversation moved to inbox.' : 'Conversation removed from your inbox.'));
        }
        redirect($messagesUrl . '?view=' . $view);
    }
}

$contacts = all("SELECT user_id,full_name,role FROM users WHERE status='active' AND user_id<>? ORDER BY full_name", [$uid]);
$view = ($_GET['view'] ?? '') === 'archived' ? 'archived' : 'inbox';
$archived = $view === 'archived' ? 1 : 0;
$directInbox = all(
    "SELECT 'direct' conversation_type,p.user_id conversation_id,p.full_name title,p.role subtitle,
            m.body last_message,m.sender_id last_sender_id,s.full_name last_sender_name,m.created_at last_created_at,
            (SELECT COUNT(*) FROM messages unread
             WHERE unread.group_id IS NULL AND unread.recipient_id=? AND unread.sender_id=p.user_id AND unread.is_read=0) unread_count
     FROM users p
     JOIN messages m ON m.message_id=(
       SELECT MAX(m2.message_id) FROM messages m2
       WHERE m2.group_id IS NULL AND
         ((m2.sender_id=? AND m2.recipient_id=p.user_id) OR (m2.sender_id=p.user_id AND m2.recipient_id=?))
     )
     JOIN users s ON s.user_id=m.sender_id
     LEFT JOIN user_conversations uc ON uc.user_id=? AND uc.conversation_type='direct' AND uc.conversation_id=p.user_id
     WHERE p.status='active' AND p.user_id<>? AND COALESCE(uc.is_deleted,0)=0
       AND COALESCE(uc.is_archived,0)=?
     ORDER BY m.created_at DESC",
    [$uid, $uid, $uid, $uid, $uid, $archived]
);
$groupInbox = all(
    "SELECT 'group' conversation_type,g.group_id conversation_id,g.group_name title,'Group chat' subtitle,
            m.body last_message,m.sender_id last_sender_id,s.full_name last_sender_name,
            COALESCE(m.created_at,g.created_at) last_created_at,0 unread_count
     FROM group_members gm
     JOIN group_chats g ON g.group_id=gm.group_id
     LEFT JOIN messages m ON m.message_id=(
       SELECT MAX(m2.message_id) FROM messages m2 WHERE m2.group_id=g.group_id
     )
     LEFT JOIN users s ON s.user_id=m.sender_id
     LEFT JOIN user_conversations uc ON uc.user_id=gm.user_id AND uc.conversation_type='group' AND uc.conversation_id=g.group_id
     WHERE gm.user_id=? AND COALESCE(uc.is_deleted,0)=0 AND COALESCE(uc.is_archived,0)=?
     ORDER BY COALESCE(m.created_at,g.created_at) DESC",
    [$uid, $archived]
);
$inbox = array_merge($directInbox, $groupInbox);
usort($inbox, fn($a, $b) => strtotime($b['last_created_at']) <=> strtotime($a['last_created_at']));

$gid = (int)($_GET['group'] ?? 0);
$contactId = (int)($_GET['contact'] ?? 0);
$group = null;
$contact = null;
$rows = [];
if ($gid) {
    $group = one('SELECT g.* FROM group_chats g JOIN group_members gm ON gm.group_id=g.group_id WHERE g.group_id=? AND gm.user_id=?', [$gid, $uid]);
    if (!$group) {
        flash('Group not found or you are not a member.', 'err');
        redirect($messagesUrl);
    }
    $rows = all('SELECT m.*,u.full_name sname,u.role srole FROM messages m JOIN users u ON u.user_id=m.sender_id WHERE m.group_id=? ORDER BY m.created_at ASC,m.message_id ASC LIMIT 200', [$gid]);
} elseif ($contactId) {
    $contact = one("SELECT user_id,full_name,role FROM users WHERE user_id=? AND status='active' AND user_id<>?", [$contactId, $uid]);
    if (!$contact) {
        flash('Person not available.', 'err');
        redirect($messagesUrl);
    }
    $rows = all(
        'SELECT m.*,u.full_name sname,u.role srole FROM messages m JOIN users u ON u.user_id=m.sender_id
         WHERE m.group_id IS NULL AND ((m.sender_id=? AND m.recipient_id=?) OR (m.sender_id=? AND m.recipient_id=?))
         ORDER BY m.created_at ASC,m.message_id ASC LIMIT 200',
        [$uid, $contactId, $contactId, $uid]
    );
    q('UPDATE messages SET is_read=1 WHERE recipient_id=? AND sender_id=?', [$uid, $contactId]);
}

page_start('Messages', $area, 'messages');
?>
<section class="card glass inbox-card">
  <div class="card-head">
    <div><h3>Inbox</h3><small>Review sent and received messages and manage your conversations.</small></div>
    <div class="row">
      <form method="get" class="row">
        <select name="contact"><option value="">Direct message…</option>
          <?php foreach ($contacts as $c): ?><option value="<?= $c['user_id'] ?>" <?= $contactId === (int)$c['user_id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?> (<?= e($c['role']) ?>)</option><?php endforeach; ?>
        </select>
        <button class="btn sm">Open</button>
      </form>
      <button class="btn sm" data-dialog="groupDlg">Create group</button>
    </div>
  </div>

  <nav class="inbox-tabs">
    <a class="btn sm <?= $view === 'inbox' ? '' : 'ghost' ?>" href="<?= url($messagesUrl) ?>">Inbox</a>
    <a class="btn sm <?= $view === 'archived' ? '' : 'ghost' ?>" href="<?= url($messagesUrl . '?view=archived') ?>">Archived</a>
  </nav>

  <?php if ($inbox): ?>
    <div class="inbox-list">
      <?php foreach ($inbox as $item):
          $isGroup = $item['conversation_type'] === 'group';
          $itemUrl = url($messagesUrl . ($isGroup ? '?group=' : '?contact=') . (int)$item['conversation_id']);
          $isMine = (int)$item['last_sender_id'] === $uid;
          $direction = $isGroup ? ($isMine ? 'You sent' : e(($item['last_sender_name'] ?? '') . ' sent')) : ($isMine ? 'Sent to' : 'Received from');
      ?>
        <article class="inbox-item">
          <a class="inbox-open" href="<?= $itemUrl ?>">
            <span class="inbox-heading"><b><?= e($item['title']) ?></b><small><?= e($item['subtitle']) ?> · <?= dt($item['last_created_at'], 'M j, g:i A') ?></small></span>
            <span class="inbox-preview"><?php if ($item['last_message'] === null): ?>No messages yet. Open this group to start chatting.<?php else: ?><b><?= $direction ?><?= !$isGroup ? ' ' . e($item['title']) : '' ?>:</b> <?= e($item['last_message']) ?><?php endif; ?></span>
            <?php if ((int)$item['unread_count'] > 0): ?><span class="badge b-pending"><?= (int)$item['unread_count'] ?> unread</span><?php endif; ?>
          </a>
          <form method="post" class="inbox-actions">
            <?= csrf_field() ?>
            <input type="hidden" name="conversation_type" value="<?= e($item['conversation_type']) ?>">
            <input type="hidden" name="conversation_id" value="<?= (int)$item['conversation_id'] ?>">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <?php if ($view === 'archived'): ?>
              <button class="btn ghost sm" name="action" value="unarchive">Move to inbox</button>
            <?php else: ?>
              <button class="btn ghost sm" name="action" value="archive">Archive</button>
            <?php endif; ?>
            <button class="btn danger sm" name="action" value="delete" onclick="return confirm('Remove this conversation from your inbox? Messages remain visible to other participants.')">Delete</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty"><?= $view === 'archived' ? 'No archived conversations.' : 'Your inbox is empty. Choose a registered user to start a direct conversation or create a group chat.' ?></div>
  <?php endif; ?>
</section>

<?php if ($group || $contact): ?>
<section class="card glass message-thread">
  <div class="card-head"><div><h3><?= $group ? e($group['group_name']) : 'Chat with ' . e($contact['full_name']) ?></h3><small><?= $group ? 'Group conversation' : 'Direct conversation' ?></small></div></div>
  <div class="message-history">
    <?php foreach ($rows as $m): $mine = (int)$m['sender_id'] === $uid; ?>
      <div class="msg <?= $mine ? 'mine' : '' ?>">
        <small><b><?= $mine ? 'You' : e($m['sname']) ?></b> (<?= e($m['srole']) ?>) · <?= dt($m['created_at'], 'M j, g:i A') ?></small>
        <?php if ($m['subject'] !== ''): ?><b><?= e($m['subject']) ?></b><br><?php endif; ?>
        <?= nl2br(e($m['body'])) ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="empty">No messages yet. Start the conversation below.</div><?php endif; ?>
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="send">
    <input type="hidden" name="group_id" value="<?= $gid ?>">
    <input type="hidden" name="recipient_id" value="<?= $contactId ?>">
    <div class="fg">
      <div class="full"><label>Subject (optional)</label><input name="subject"></div>
      <div class="full"><label>Message</label><textarea name="body" required></textarea></div>
    </div>
    <div class="row end" style="margin-top:12px"><button class="btn">Send message</button></div>
  </form>
</section>
<?php endif; ?>

<dialog id="groupDlg">
  <form method="post">
    <h3>Create group chat</h3><?= csrf_field() ?>
    <input type="hidden" name="action" value="create_group">
    <div class="fg">
      <div class="full"><label>Group name</label><input name="group_name" required maxlength="120"></div>
      <div class="full"><label>Choose members (select multiple)</label><select name="members[]" multiple size="7" required style="min-height:150px">
        <?php foreach ($contacts as $c): ?><option value="<?= $c['user_id'] ?>"><?= e($c['full_name']) ?> — <?= e(ucfirst($c['role'])) ?></option><?php endforeach; ?>
      </select><small>You are added automatically as the creator.</small></div>
    </div>
    <div class="row end" style="margin-top:14px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Create group</button></div>
  </form>
</dialog>
<?php page_end();
