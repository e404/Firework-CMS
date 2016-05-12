
				</div>
				<div id="footer">
					<row>
						<box>
							&copy; Copyright 2015-<?= date('Y') ?> <a href="<?= App::getLink('/') ?>"><?= Config::get('legal', 'company_nicename') ?></a><br>
						</box>
					</row>
				</div>
			</div>
		</div>
	[[[HOOK:after-footer]]]
	</body>
</html>