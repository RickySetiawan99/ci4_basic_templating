<?php if (session()->has('message')) : ?>
	<div class="alert alert-success">
		<?= session('message') ?>
	</div>
<?php endif ?>

<?php if (session()->has('error')) : ?>
	<div class="alert alert-danger text-white">
		<?= session('error') ?>
	</div>
<?php endif ?>

<?php if (session()->has('errors')) : ?>
	<ul class="alert alert-danger text-white px-5">
	<?php foreach (session('errors') as $error) : ?>
		<li class=""><?= $error ?></li>
	<?php endforeach ?>
	</ul>
<?php endif ?>
