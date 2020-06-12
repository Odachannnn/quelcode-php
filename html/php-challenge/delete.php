<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
	$id = $_REQUEST['id'];
	
	// 投稿を検査する
	$messages = $db->prepare('SELECT * FROM posts WHERE id=?');
	$messages->execute(array($id));
	$message = $messages->fetch();

	if ($message['member_id'] == $_SESSION['id']) {
		// 削除する
		$del = $db->prepare('DELETE FROM posts WHERE id=?');
		$del->execute(array($id));
	}
	//もしその投稿がリツイートされていたら、リツイートされた全ての投稿も削除する
	$retweetDelete = $db->prepare('DELETE FROM posts WHERE retwi_origin_id=?');
	$retweetDelete->execute(array($id));
	
	//削除された投稿の情報をrepostsテーブルからも削除する
	$retweetInfoDel = $db->prepare('DELETE FROM reposts WHERE origin_id=?');
	$retweetInfoDel->execute(array($message['retwi_origin_id']));
}
header('Location: index.php'); exit();
?>
