<?php
/* @var $this ANS_Moderators */

$user = wp_get_current_user();

if ($user->user_nicename != $moderator && !is_super_admin()) {
    exit;
}

$prices = $this->moderators[$moderator];

// Wordpress default timezone is UTC
$posts = $this->get_user_posts($moderator);
$posts_cur_month = $this->filter_posts_by_date('first day of this month 00:00:00', 'now', $posts);
$posts_prev_month = $this->filter_posts_by_date('first day of previous month 00:00:00', 'last day of previous month 23:59:59', $posts);

$referrals = $this->get_referrals($moderator);
$referrals_cur_month = $this->filter_users_by_date('first day of this month 00:00:00', 'now', $referrals);
$referrals_prev_month = $this->filter_users_by_date('first day of previous month 00:00:00', 'last day of previous month 23:59:59', $referrals);

get_header();
?>

<h2>Панель модератора</h2>

<p>
    Для приглашения пользователей на сайт к адресу любой страницы добавьте: <b>#<?= $moderator ?></b> <br>
    Например: <?= site_url() . "#$moderator" ?>
</p>

<p>
    Оплата за вашу работу: <br>
    - за каждую 1000 символов в сообщениях на форуме: <?= $prices['per_1000'] ?> руб. <br>
    - за каждого зарегистрированного пользователя: <?= $prices['registered'] ?> руб. <br>
    - за заполнение пользователем профиля (пол, дата рождения, город, социотип, фото, аватар): <?= $prices['profile'] ?> руб. <br>
    - за написание пользователем <?= $this->posts_to_count_forum ?> сообщений на форуме: <?= $prices['forum'] ?> руб.
</p>

<p>
    Вами написано постов на форуме всего: <?= count($posts) ?>. <br>

    За текущий месяц постов: <?= count($posts_cur_month) ?>.
    Знаков (с пробелами): <?= $text_size_cur_month = $this->count_posts_text_size($posts_cur_month) ?>.
    Заработано: <?= $money_posts_cur_month = (int) ($text_size_cur_month / 1000 * $prices['per_1000']) ?> руб. <br>

    За предыдущий месяц постов: <?= count($posts_prev_month) ?>.
    Знаков (с пробелами): <?= $text_size_prev_month = $this->count_posts_text_size($posts_prev_month) ?>.
    Заработано: <?= $money_posts_prev_month = (int) ($text_size_prev_month / 1000 * $prices['per_1000']) ?> руб. <br>
</p>

<p>
    По вашим ссылкам зарегистрировано пользователей всего: <?= count($referrals) ?>. <br>
    За текущий месяц: <?= count($referrals_cur_month) ?>. <br>
    За предыдущий месяц: <?= count($referrals_prev_month) ?>. <br>
    * Пользователь считается зарегистрированным по прошествии <?= (int) $this->time_to_count_user ?> дней после заполнения формы регистрации.
</p>

<a href="#">Список пользователей за текущий месяц:</a>

<?php
$referrals_list = $referrals_cur_month;
require 'referrals.php';
?>

<h2>Итоговый заработок за текущий месяц: <?= $money_posts_cur_month + $money_referrals ?> руб.</h2>

<a href="#">Список пользователей за предыдущий месяц:</a>

<?php
$referrals_list = $referrals_prev_month;
require 'referrals.php';
?>

<h2>Итоговый заработок за предыдущий месяц: <?= $money_posts_prev_month + $money_referrals ?> руб.</h2>

<script>
    jQuery(document).ready(function ($) {
        $('table').prev().attr('title', 'Показать / Скрыть список').click(function () {
            $(this).next().toggle();
            return false;
        });
    });
</script>

<?php
get_footer();
