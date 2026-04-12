<?php foreach ($order['items'] as $item): ?>
    <p>
        <?= $item['quantity'] ?>x <?= htmlspecialchars($item['item']['name']) ?>
        <?php if (!empty($item['options'])): ?>
            <br><small>
            <?php foreach ($item['options'] as $opt): ?>
                • <?= htmlspecialchars($opt['option_name']) ?>: <?= htmlspecialchars($opt['value_name']) ?>
                <?php if ($opt['price_modifier'] != 0): ?>
                    (<?= ($opt['price_modifier'] > 0 ? '+' : '-') ?>$<?= number_format(abs($opt['price_modifier']), 2) ?>)
                <?php endif; ?><br>
            <?php endforeach; ?>
            </small>
        <?php endif; ?>
        – $<?= number_format($item['subtotal'], 2) ?>
    </p>
<?php endforeach; ?>