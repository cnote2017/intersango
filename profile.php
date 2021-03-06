<?php
require_once 'openid.php';
require_once 'util.php';
require_once 'view_util.php';

global $oidlogin;

if (!$is_logged_in)
    throw new Error(_('Denied'), _('Go away.'));

echo "   <div class='content_box'>\n";
echo "<h3>" . _("Private user info") . "</h3>\n";
// main info
echo "<p>" . _("You are logged in.") . "</p>\n";
$uid = $is_logged_in;
echo "<p>" . _("User ID") . ": $uid</p>\n";
echo "<p>" . _("OpenID") . ": $oidlogin</p>\n";
show_balances($uid);
show_committed_balances($uid);
check_fiat_balance_limit($uid, "0");
echo "</div>\n";

$query = "
    SELECT
        orderid,
        amount,
        initial_amount,
        type,
        initial_want_amount,
        want_type,
        " . sql_format_date("timest") . " AS timest,
        status
    FROM orderbook
    WHERE uid='$uid'
    ORDER BY orderbook.timest DESC;
";
$result = do_query($query);
$row = mysql_fetch_assoc($result);
if ($row) { ?>
    <div class='content_box'>
    <h3><?php echo _("Your orders"); ?></h3>
    <table class='display_data'>
        <tr>
            <th class='right'><?php echo _("Giving"); ?></th>
            <th class='right'><?php echo _("Wanted"); ?></th>
            <th class='right'><?php echo _("Price"); ?></th>
            <th><?php echo _("Time"); ?></th>
            <th><?php echo _("Status"); ?><br/>(<?php echo _("% matched"); ?>)</th>
            <th><?php echo _("Trades"); ?></th>
        </tr><?php
    do {
        $orderid = $row['orderid'];
        $amount = $row['amount'];
        $initial_amount = $row['initial_amount'];
        $type = $row['type'];
        $initial_want_amount = $row['initial_want_amount'];
        $want_type = $row['want_type'];
        $timest = $row['timest'];
        // $timest = str_replace(" ", "<br/>", $timest);
        $status_code = $row['status'];
        $status = translate_order_code($status_code);
        $price = $type == 'BTC'
            ? fiat_and_btc_to_price($initial_want_amount, $initial_amount)
            : fiat_and_btc_to_price($initial_amount, $initial_want_amount);
        $percent_complete = sprintf("%.0f", bcdiv(gmp_strval(gmp_mul(gmp_sub($initial_amount, $amount), 100)), $initial_amount, 1));
        $trade_count = count_transactions($orderid);
        $give_precision = $type == 'BTC' ? BTC_PRECISION : FIAT_PRECISION;
        $want_precision = $type == 'BTC' ? FIAT_PRECISION : BTC_PRECISION;
        echo "    ", active_table_row("active", "?page=view_order&orderid=$orderid"), "\n";
        echo "        <td class='right'>" . internal_to_numstr($initial_amount,      $give_precision) . "&nbsp;$type</td>\n";
        echo "        <td class='right'>" . internal_to_numstr($initial_want_amount, $want_precision) . "&nbsp;$want_type</td>\n";
        echo "        <td class='right'>$price</td>\n";
        echo "        <td>$timest</td>\n";
        echo "        <td>$status ($percent_complete%)</td>\n";
        echo "        <td>$trade_count</td>\n";
        echo "    </tr>\n";
    } while ($row = mysql_fetch_assoc($result));
    echo "</table></div>";
}

// also used when you view an order
display_transactions($uid, 0);

$query = "
    SELECT
        reqid,
        req_type,
        amount,
        curr_type,
        " . sql_format_date("timest") . " AS timest,
        status
    FROM requests
    WHERE
        uid='$uid' 
        AND (req_type='WITHDR' OR req_type='DEPOS') 
        AND status!='IGNORE'
    ORDER BY requests.timest DESC;
";
$result = do_query($query);
$row = mysql_fetch_assoc($result);
if ($row) { ?>
    <div class='content_box'>
    <h3><?php echo _("Your requests"); ?></h3>
    <table class='display_data'>
        <tr>
            <th><?php echo _("Amount"); ?></th>
            <th><?php echo _("Time"); ?></th>
            <th><?php echo _("Status"); ?></th>
            <th></th>
        </tr><?php
    do {
        $reqid = $row['reqid'];
        $req_type = $row['req_type'];
        $req_type = translate_request_type($req_type);
        $amount = internal_to_numstr($row['amount']);
        $curr_type = $row['curr_type'];
        $timest = $row['timest'];
        $status = $row['status'];
        $status = translate_request_code($status);
        echo "    <tr>\n";
        echo "        <td>$req_type $amount $curr_type</td>\n";
        echo "        <td>$timest</td>\n";
        echo "        <td>$status</td>\n";
        echo "        <td><a href='?page=view_request&reqid=$reqid'>" . _("View request") . "</a></td>\n";
        echo "    </tr>\n";
    } while ($row = mysql_fetch_assoc($result));
    echo "</table></div>";
}

try {
    $needed_conf = CONFIRMATIONS_FOR_DEPOSIT;
    $balance = bitcoin_get_balance($uid, $needed_conf);

    if ($balance != bitcoin_get_balance($uid, 0)) { ?>
    <div class='content_box'>
    <h3><?php echo _("Pending bitcoin deposits"); ?></h3>
    <table class='display_data'>
        <tr>
            <th><?php echo _("Amount"); ?></th>
            <th><?php echo _("Confirmations Received"); ?></th>
            <th><?php echo _("More Confirmations Needed"); ?></th>
        </tr>
    <?php
        for ($conf = $needed_conf; $conf >= 0; $conf--) {
            $new_balance = bitcoin_get_balance($uid, $conf);
            if ($balance != $new_balance) {
                $diff = gmp_sub($new_balance, $balance);
                echo "<tr><td>", internal_to_numstr($diff), "</td><td>$conf</td><td>", $needed_conf - $conf, "</td></tr>\n";
                $balance = $new_balance;
            }
        }
        echo "</table></div>";
    }
} catch (Exception $e) {
    if ($e->getMessage() != 'Unable to connect.')
        throw $e;
    echo "<div class='content_box'>\n";
    echo "<h3>" . _("Pending bitcoin deposits") . "</h3>\n";
    echo "<p>" . _("Normally this area would display any Bitcoin deposits you have made that are awaiting confirmations, but we are having trouble connecting to the Bitcoin network at the moment, so it doesn't.") . "</p>\n";
    echo "<p>" . _("Please try again in a few minutes.") . "</p>\n";
    echo "</div>";
}
?>
