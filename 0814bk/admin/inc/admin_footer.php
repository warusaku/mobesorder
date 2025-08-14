<?php
// 共通管理フッター  admin/inc/admin_footer.php
// ------------------------------------------------------------
// admin_header.php で開いたタグを閉じ、ページ共通スクリプトを読み込む
// ------------------------------------------------------------
?>
        </div><!-- /.container -->
        <footer class="site-footer">
            <?php echo htmlspecialchars(($footerDesign['footer_text'] ?? '© 2025 Mobes.Online')); ?>
        </footer>
        <!-- 共通スクリプト -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="js/logscan.js"></script>
        <script>
        // ページ内で二重挿入された .header / .nav-pills を削除
        document.addEventListener('DOMContentLoaded',()=>{
            const headers=[...document.querySelectorAll('.container>.header')];
            headers.slice(1).forEach(h=>h.remove());
            const navs=[...document.querySelectorAll('.container>ul.nav-pills')];
            navs.slice(1).forEach(n=>n.remove());
        });
        </script>
    </body>
</html> 