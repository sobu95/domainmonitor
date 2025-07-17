<div class="alert alert-info">
    <h5><i class="fas fa-clock"></i> Konfiguracja zadań Cron</h5>
    <p>Aby system działał automatycznie, dodaj następujące zadania cron do swojego hostingu:</p>
    
    <div class="mb-3">
        <h6>1. Codzienne pobieranie domen (9:05)</h6>
        <code class="d-block bg-dark text-light p-2 rounded">
            5 9 * * * /usr/bin/php <?php echo realpath('.'); ?>/cron/fetch_domains.php
        </code>
    </div>
    
    <div class="mb-3">
        <h6>2. Wysyłanie przypomnień (8:00)</h6>
        <code class="d-block bg-dark text-light p-2 rounded">
            0 8 * * * /usr/bin/php <?php echo realpath('.'); ?>/cron/send_reminders.php
        </code>
    </div>
    
    <div class="mb-3">
        <h6>3. Czyszczenie starych logów (2:00, raz w tygodniu)</h6>
        <code class="d-block bg-dark text-light p-2 rounded">
            0 2 * * 0 /usr/bin/php <?php echo realpath('.'); ?>/cron/cleanup.php
        </code>
    </div>
    
    <div class="alert alert-warning mt-3">
        <strong>Uwaga:</strong> Ścieżka do PHP może być inna na Twoim hostingu. Sprawdź w panelu hostingu lub skontaktuj się z dostawcą.
        Typowe ścieżki: <code>/usr/bin/php</code>, <code>/usr/local/bin/php</code>, <code>/opt/php/bin/php</code>
    </div>
</div>

<div class="text-center mt-4">
    <a href="index.php" class="btn btn-success btn-lg">
        <i class="fas fa-home"></i> Przejdź do systemu
    </a>
</div>