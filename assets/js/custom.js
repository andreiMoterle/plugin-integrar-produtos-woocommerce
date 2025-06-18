document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            var loader = document.getElementById('importador-loader');
            if (loader) loader.style.display = 'block';
        });
    });
});