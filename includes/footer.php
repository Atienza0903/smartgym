        </div>
        <footer class="app-footer">&copy; <?= date('Y') ?> <?= e(setting('gym_name', "Gleah's Fitness Gym")) ?> · <?= e(APP_NAME) ?></footer>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="assets/js/app.js"></script>
<?php if (!empty($page_scripts)) echo $page_scripts; ?>
</body>
</html>
