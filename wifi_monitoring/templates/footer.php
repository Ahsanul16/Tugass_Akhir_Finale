<?php
/**
 * Template Footer
 */

$base_url = getBaseUrl();
$current_year = date('Y');

?>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light py-4 mt-5 border-top">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-center text-md-start">
                    <p class="mb-0">
                        <strong><i class="bi bi-wifi"></i> Monitoring Access Point</strong>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo $base_url; ?>/assets/js/main.js"></script>
    
    <?php if (isset($additional_js)): ?>
        <?php echo $additional_js; ?>
    <?php endif; ?>
</body>
</html>
