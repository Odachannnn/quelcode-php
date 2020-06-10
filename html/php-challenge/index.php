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
if ($_POST['like'] === 'add') {
	$addLike = $db->prepare('INSERT INTO likes SET like_member_id=?, like_post_id=?, created=NOW()');
	$addLike->execute(array(
		$member['id'],
		$_POST['like_post_id']
	));
	header('Location: index.php'); //二重いいねの防止
	exit;
}
//いいねを削除する
//あるアカウントが、ある投稿にいいねをできるのは１回限り。なので、like_member_idとlike_post_idで消したいidを特定できる！！✌︎('ω'✌︎ )
if ($_POST['like'] === 'delete') {
	$getLikeId = $db->prepare('SELECT id FROM likes WHERE like_member_id=? AND like_post_id=?');
	$getLikeId->execute(array(
		$member['id'],
		$_POST['like_post_id']
	));
	$likeId = $getLikeId->fetch();
	$deleteLike = $db->prepare('DELETE FROM likes WHERE id=?');
	$deleteLike->execute(array($likeId['id']));
	header('Location: index.php');
	exit;
}
//投稿ごとのいいね数を取得するにはlikesテーブルの各like_post_idをカウントしたらいけると思ったんだけどな=>いけた！！✌︎('ω'✌︎ )
//$post['id']の値が必要なので、foreach文内でSQLを実行した
$numsOfLike = $db->prepare('SELECT COUNT(id) AS cntLike FROM likes WHERE like_post_id=?');

//いいね機能終わり
//以下リツイート機能

//リツイートする
if ($_POST['retweet'] === 'send') {
	//ある投稿を再投稿する
	$reposts = $db->prepare('INSERT INTO posts SET message=?, member_id=?, retwi_member_id=?, retwi_origin_id=?, created=NOW()');
	$reposts->execute(array(
		$_POST['origin_message'],
		$_POST['origin_member_id'],
		$member['id'],
		$_POST['origin_id']
	));
	//リツイート情報を登録する
	$recordReposts = $db->prepare('INSERT INTO reposts SET my_member_id=?, origin_member_id=?, origin_id=?, created=NOW()');
	$recordReposts->execute(array(
		$member['id'],
		$_POST['origin_member_id'],
		$_POST['origin_id']
	));
	//リツイート元の投稿にいいねがついていた場合どうする？？
	//like_post_id=reposts.origin_id
	header('Location: index.php');
	exit();
}
//リツイートを取り消す
if ($_POST['retweet'] === 'delete') {
	//まず消したいリツイートを特定する。
	//一つのアカウントが一つの投稿をリツイートできるのは１回限りなので、そこから特定
	//repostsテーブルからの削除
	$getRetweetId = $db->prepare('SELECT id FROM reposts WHERE my_member_id=? AND origin_id=?');
	$getRetweetId->execute(array(
		$member['id'],
		$_POST['origin_id']
	));
	$retweetId = $getRetweetId->fetch();
	$deleteRetweetInfo = $db->prepare('DELETE FROM reposts WHERE id=?');
	$deleteRetweetInfo->execute(array($retweetId['id']));

	//postsテーブルからの削除
	//post.idの特定
	$getPostsId = $db->prepare('SELECT id FROM posts WHERE retwi_member_id=? AND retwi_origin_id=?');
	$getPostsId->execute(array(
		$member['id'],
		$_POST['origin_id']
	));
	$postId = $getPostsId->fetch();
	$deleteRepost = $db->prepare('DELETE FROM posts WHERE id=?');
	$deleteRepost->execute(array($postId['id']));
	header('Location: index.php');
	exit;
}
//ログインしている人が以前行ったリツイートを取得する
//一つのアカウントが一つの投稿をリツイートできるのは１回限り。
//repostsテーブルからログインしている人の、リツイートしたorigin_idを取得する
$getRetweets = $db->prepare('SELECT origin_id FROM reposts WHERE my_member_id=? ORDER BY origin_id ASC');
$getRetweets->execute(array($member['id']));
while ($getRetweet = $getRetweets->fetchColumn()) {
	$retweetArr[] = (int) $getRetweet;
};

//一つの投稿に関して、リツイートされた回数を調べるため、プリペアドステートメントを準備する
$cntRetweets = $db->prepare('SELECT COUNT(id) AS cntRetweet FROM reposts WHERE origin_id=?');

//リツイートした人の情報を取得するためのプリペアドステートメントを準備する
$retMember = $db->prepare('SELECT m.name FROM members m, posts p WHERE m.id=p.retwi_member_id AND p.id=?');


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
//バイナリサーチ（ログインした人が以前いいね/RTをしたリストの中に、post['id']が含まれているかどうか、true/falseで返す）
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

			<!--ここから先の構造-->
			<!--その投稿が、リツイートされたものであるか否かをまず判断する-->

			<!--リツイートされたものである場合-->
			
			<!--いいね機能-->
			<!--いいねをすると、元の投稿のいいね数に反映させたい=>likesテーブルのlike_post_idには、postsテーブルのretwi_origin_idを渡す✌︎('ω'✌︎ )-->
			<!--リツイート後の投稿にも、いいね数を反映させたい=>処置は上記同様✌︎('ω'✌︎ )-->
			<!--リツイート後の投稿からも、いいねを取り消せるようにしたい=>処置は上記同様✌︎('ω'✌︎ )-->
			
			<!--リツイート機能-->
			<!--リツイートをすると、元の投稿のリツイート数に反映させたい=>repostsテーブルのorigin_idに、postsテーブルのretwi_origin_idを渡してみる✌︎('ω'✌︎ )-->
			<!--リツイート後の投稿にも、リツイート数を反映させたい=>処置は上記同様✌︎('ω'✌︎ )-->
			<!--リツイート後の投稿からも、リツイートを取り消したい=>処置は上記同様✌︎('ω'✌︎ )-->
			<!--その際、再投稿されたpost自体も削除したい-->
			<!--一つのアカウントが、一つの投稿に対してリツイートできるのは１回限り。そのため、postsテーブルのidを特定するには、postsテーブルにリツイートした人のカラムが必要-->
			<!--retwi_member_idとretwi_origin_idでposts.idを特定して削除できるかも=>できた✌︎('ω'✌︎ )-->

			<!--リツイートされたものではない場合-->
			

			<?php foreach ($posts as $post) : ?>
				<div class="msg">
					<!--その投稿がリツイートされたものだった場合、リツイートした人を表示する-->
					<?php if ($post['retwi_origin_id'] > 0) {
						$retMember->execute(array($post['id']));
						$retMemberName = $retMember->fetch();
						?>
						<img src="images/inforetweet.png" alt="リツイートされました" width="12px" height="12px">
						<p class="msg__retweet">Retweeted by <?php echo $retMemberName['name']; ?></p>
					<?php } ?>
					<!--表示終わり-->
					<img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
					<p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
					<div class="post_info">
						<p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a></p>
						<p class="like">
							<?php
							//もしその投稿がリツイートされたものだった場合

							if ($post['retwi_origin_id'] > 0) { 
								//likesテーブルにはposts.idではなくretwi_origin_idを渡す
								$numsOfLike->execute(array($post['retwi_origin_id']));
								$cntLike = $numsOfLike->fetchColumn();
								//まずいいね機能から
								if (!binarySearch($likePostArr, $post['retwi_origin_id'])) : ?>
									<!--その投稿にいいねしたことがないならば？-->
									<!--いいね機能-->
									<form action="" method="post">
										<input type="hidden" name="like" value="add">
										<input type="hidden" name="like_post_id" value="<?php echo $post['retwi_origin_id']; ?>">
										<input type="image" name="like" src="images/like.png" alt="いいね" width="13px" height="13px">
										<?php if ($cntLike > 0) : ?>
											<input type="submit" class="like_form" value="<?php echo h($cntLike); ?>">
										<?php endif; ?>
									</form>
								<?php else : ?>
									<!--いいね取り消し機能-->
									<form action="" method="post">
										<input type="hidden" name="like" value="delete">
										<input type="hidden" name="like_post_id" value="<?php echo $post['retwi_origin_id']; ?>">
										<input type="image" src="images/after_like.png" alt="いいね取り消し" width="13px" height="13px">
										<?php if ($cntLike > 0) : ?>
											<input type="submit" class="like_form on_like_form" value="<?php echo h($cntLike); ?>">
										<?php endif; ?>
									</form>
								<?php endif; ?>
								<!--以下リツイート機能-->
								<?php
								$cntRetweets->execute(array($post['retwi_origin_id']));
								$cntRetweet = $cntRetweets->fetchColumn();
								if (!binarySearch($retweetArr, $post['retwi_origin_id'])) : ?>
									<!--投稿に対して、リツイートしたことがない場合-->
									<form action="" method="post">
										<input type="hidden" name="retweet" value="send">
										<input type="hidden" name="origin_message" value="<?php echo ($post['message']); ?>">
										<input type="hidden" name="origin_member_id" value="<?php echo ($post['member_id']); ?>">
										<input type="hidden" name="origin_id" value="<?php echo ($post['retwi_origin_id']); ?>">
										<input type="image" src="images/retweet.png" alt="リツイートする" width="13px" height="13px">
										<?php if ($cntRetweet > 0) : ?>
											<input type="submit" class="like_form" value="<?php echo h($cntRetweet); ?>">
										<?php endif; ?>
									</form>
								<?php else : ?>
									<!--リツイートしたことがある＝取り消す-->
									<form action="" method="post">
										<input type="hidden" name="retweet" value="delete">
										<input type="hidden" name="origin_id" value="<?php echo h($post['retwi_origin_id']); ?>">
										<input type="hidden" name="afterRetwiId" value="<?php echo h($post['id']); ?>">
										<input type="image" src="images/after_retweet.png" alt="リツイートを取り消す" width="13px" height="13px">
										<?php if ($cntRetweet > 0) : ?>
											<input type="submit" class="like_form on_retweet_form" value="<?php echo h($cntRetweet); ?>">
										<?php endif; ?>
									</form>
								<?php endif; ?>

								<?php
								//その投稿が、リツイートされたものではない場合
							} else {
								$numsOfLike->execute(array($post['id'])); //いいね数をカウントするプリペアドステートメントの、likes.like_post_idにその投稿ごとのposts.idを渡している
								$cntLike = $numsOfLike->fetchColumn();
								if (!binarySearch($likePostArr, $post['id'])) : ?>
									<!--その投稿にいいねしたことがないならば？-->
									<!--いいね機能-->
									<form action="" method="post">
										<input type="hidden" name="like" value="add">
										<input type="hidden" name="like_post_id" value="<?php echo $post['id']; ?>">
										<input type="image" name="like" src="images/like.png" alt="いいね" width="13px" height="13px">
										<?php if ($cntLike > 0) : ?>
											<input type="submit" class="like_form" value="<?php echo h($cntLike); ?>">
										<?php endif; ?>
									</form>
								<?php else : ?>
									<!--いいね取り消し機能-->
									<form action="" method="post">
										<input type="hidden" name="like" value="delete">
										<input type="hidden" name="like_post_id" value="<?php echo $post['id']; ?>">
										<input type="image" src="images/after_like.png" alt="いいね取り消し" width="13px" height="13px">
										<?php if ($cntLike > 0) : ?>
											<input type="submit" class="like_form on_like_form" value="<?php echo h($cntLike); ?>">
										<?php endif; ?>
									</form>
								<?php endif; ?>
								<!--以下リツイート機能-->
								<?php
								$cntRetweets->execute(array($post['id']));
								$cntRetweet = $cntRetweets->fetchColumn();
								if (!binarySearch($retweetArr, $post['id'])) : ?>
									<!--投稿に対して、リツイートしたことがない場合-->
									<form action="" method="post">
										<input type="hidden" name="retweet" value="send">
										<input type="hidden" name="origin_message" value="<?php echo ($post['message']); ?>">
										<input type="hidden" name="origin_member_id" value="<?php echo ($post['member_id']); ?>">
										<input type="hidden" name="origin_id" value="<?php echo ($post['id']); ?>">
										<input type="image" src="images/retweet.png" alt="リツイートする" width="13px" height="13px">
										<?php if ($cntRetweet > 0) : ?>
											<input type="submit" class="like_form" value="<?php echo h($cntRetweet); ?>">
										<?php endif; ?>
									</form>
								<?php else : ?>
									<!--リツイートしたことがある＝取り消す-->
									<form action="" method="post">
										<input type="hidden" name="retweet" value="delete">
										<input type="hidden" name="origin_id" value="<?php echo h($post['id']); ?>">
										<input type="image" src="images/after_retweet.png" alt="リツイートを取り消す" width="13px" height="13px">
										<?php if ($cntRetweet > 0) : ?>
											<input type="submit" class="like_form on_retweet_form" value="<?php echo h($cntRetweet); ?>">
										<?php endif; ?>
									</form>
								<?php endif; ?>
							<?php } ?>
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
