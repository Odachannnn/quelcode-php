<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインしている
	$_SESSION['time'] = time();

	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
	$member = $members->fetch();
} else {
	// ログインしていない
	header('Location: login.php');
	exit();
}

// 投稿を記録する
if (!empty($_POST)) {
	if ($_POST['message'] != '') {
		$message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
		$message->execute(array(
			$member['id'],
			$_POST['message'],
			$_POST['reply_post_id']
		));

		header('Location: index.php');
		exit();
	}
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
	$page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

//以下いいね機能たち

//ログインしている人が以前したいいねを取得する
$getLikePosts = $db->prepare('SELECT like_post_id FROM likes WHERE like_member_id=? ORDER BY like_post_id ASC'); //ここで昇順で取り出すことを指定
$getLikePosts->execute(array($member['id']));
while ($likePost = $getLikePosts->fetchColumn()) {
	$likePostArr[] = (int) $likePost;
}
//いいねを登録する
if ($_GET['like'] === 'add') {
	$addLike = $db->prepare('INSERT INTO likes SET like_member_id=?, like_post_id=?, created=NOW()');
	$addLike->execute(array(
		$member['id'],
		$_GET['like_post_id']
	));
	header('Location: index.php'); //二重いいねの防止
	exit;
}
//いいねを削除する
//あるアカウントが、ある投稿にいいねをできるのは１回限り。なので、like_member_idとlike_post_idで消したいidを特定できる！！✌︎('ω'✌︎ )
if ($_GET['like'] === 'delete') {
	$getLikeId = $db->prepare('SELECT id FROM likes WHERE like_member_id=? AND like_post_id=?');
	$getLikeId->execute(array(
		$member['id'],
		$_GET['like_post_id']
	));
	$likeId = $getLikeId->fetch();
	$deleteLike = $db->prepare('DELETE FROM likes WHERE id=?');
	$deleteLike->execute(array($likeId['id']));
	header('Location: index.php');
	exit;
}
//投稿ごとのいいね数を取得するlikesテーブルの各like_post_idをカウントしたらいけると思ったんだけどな=>いけた！！✌︎('ω'✌︎ )
//$post['id']の値が必要なので、foreach文内でSQLを実行した
$numsOfLike = $db->prepare('SELECT COUNT(id) AS cntLike FROM likes WHERE like_post_id=?');

//いいね機能終わり

// 返信の場合
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
	$response->execute(array($_REQUEST['res']));

	$table = $response->fetch();
	$message = '@' . $table['name'] . ' ' . $table['message'];
}

// htmlspecialcharsのショートカット
function h($value)
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value)
{
	return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}
//バイナリサーチ（post['id']に対していいねをしたかどうか、true/falseで返すため）
function binarySearch($array, $target)
{
	$array = (array) $array;
	$length = count($array) - 1;
	$target = (int) $target;
	$head = 0;
	$tail = $length;
	while ($head <= $length) {
		if ($tail < $head || $target > $array[$length]) {
			return false;
		} else {
			$center = ceil(($head + $tail) / 2);
			if ($array[$center] === $target) {
				return true;
			} elseif ($array[$center] > $target) {
				$tail = $center - 1;
			} elseif ($array[$center] < $target) {
				$head = $center + 1;
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>

	<link rel="stylesheet" href="style.css" />
</head>

<body>
	<div id="wrap">
		<div id="head">
			<h1>ひとこと掲示板</h1>
		</div>
		<div id="content">
			<div style="text-align: right"><a href="logout.php">ログアウト</a></div>
			<form action="" method="post">
				<dl>
					<dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
					<dd>
						<textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
						<input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
					</dd>
				</dl>
				<div>
					<p>
						<input type="submit" value="投稿する" />
					</p>
				</div>
			</form>

			<?php foreach ($posts as $post) : ?>
				<div class="msg">
					<img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
					<p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
					<div class="post_info">
						<p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a></p>
						<p class="like">
							<?php
							$numsOfLike->execute(array($post['id']));
							$cntLike = $numsOfLike->fetchColumn();
							if (!binarySearch($likePostArr, $post['id'])) : ?>
								<!--いいね機能-->
								<form action="" method="get">
									<input type="hidden" name="like" value="add">
									<input type="hidden" name="like_post_id" value="<?php echo $post['id']; ?>">
									<input type="image" name="like" src="images/like.png" alt="いいね" width="13px" height="13px">
									<?php if ($cntLike > 0) : ?>
										<input type="submit" class="like_form" value="<?php echo h($cntLike); ?>">
									<?php endif; ?>
								</form>
							<?php else : ?>
								<!--いいね取り消し機能-->
								<form action="" method="get">
									<input type="hidden" name="like" value="delete">
									<input type="hidden" name="like_post_id" value="<?php echo $post['id']; ?>">
									<input type="image" src="images/after_like.png" alt="いいね取り消し" width="13px" height="13px">
									<?php if ($cntLike > 0) : ?>
										<input type="submit" class="like_form on_like_form" value="<?php echo h($cntLike); ?>">
									<?php endif; ?>
								</form>
							<?php endif; ?>
							<!--以下リツイート機能-->
							<form action="" method="post">
								<input type="hidden" name="retweet" value="send">
								<input type="hidden" name="retwi_post_id" value="<?php echo h($post['id']);?>">
								<input type="image" src="images/retweet.png" alt="リツイートする" width="13px" height="13px">
							</form>
							<?php if ($post['reply_post_id'] > 0) : ?>
								<a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a>
							<?php endif; ?>
							<?php if ($_SESSION['id'] == $post['member_id']) : ?>
								[<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
							<?php endif; ?>
						</p>
					</div>
				</div>
			<?php endforeach; ?>

			<ul class="paging">
				<?php
				if ($page > 1) {
				?>
					<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
				<?php
				} else {
				?>
					<li>前のページへ</li>
				<?php
				}
				?>
				<?php
				if ($page < $maxPage) {
				?>
					<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
				<?php
				} else {
				?>
					<li>次のページへ</li>
				<?php
				}
				?>
			</ul>
		</div>
	</div>
</body>

</html>