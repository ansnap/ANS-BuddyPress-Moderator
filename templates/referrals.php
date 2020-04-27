<?php /* @var $this ANS_Moderators */ ?>

<table>
    <tr>
        <th>Пользователь</th>
        <th>Создал аккаунт</th>
        <th>Заполнил профиль</th>
        <th>Постов на форуме</th>
        <th>Заработано</th>

        <?php if (is_super_admin()) : ?>
            <th>Реферер</th>
            <th>IP</th>
        <?php endif; ?>
    </tr>

    <?php $money_referrals = 0; ?>

    <?php foreach ($referrals_list as $referral) : ?>
        <?php
        $referral_posts = $this->filter_posts_by_date($referral->user_registered, "$referral->user_registered + $this->time_to_count_user", $this->get_user_posts($referral->user_nicename));
        $profile_completed = $this->has_user_completed_profile($referral->ID) && $this->avatar_date_is_fine($referral);
        ?>

        <tr>
            <td><a href="<?= bp_core_get_user_domain($referral->ID) ?>" target="_blank"><?= $referral->user_login ?></a></td>
            <td><?= $referral->user_registered ?></td>
            <td><?= $profile_completed ? 'Да' : 'Нет' ?></td>
            <td><?= count($referral_posts) ?></td>
            <td><?= $earned = $prices['registered'] + ($profile_completed ? $prices['profile'] : 0) + (count($referral_posts) >= $this->posts_to_count_forum ? $prices['forum'] : 0) ?> руб.</td>

            <?php $money_referrals += $earned; ?>

            <?php if (is_super_admin()) : ?>
                <td><?= get_user_meta($referral->ID, 'ans_referrer_url', true) ?></td>
                <td><?= get_user_meta($referral->ID, 'ans_ip', true) ?></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>

    <tr>
        <th colspan="7">Итого заработано за зарегистрированных пользователей: <?= $money_referrals ?> руб.</th>
    </tr>
</table>