        // Toggle sidebar on mobile
        document.querySelector('.menu-icon').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Show/hide custom date range
        document.getElementById('date-range').addEventListener('change', function() {
            const customDateRange = document.getElementById('custom-date-range');
            customDateRange.style.display = this.value === 'custom' ? 'flex' : 'none';
        });

        // Reset filters
        document.getElementById('reset-filters').addEventListener('click', function() {
            document.getElementById('report-type').value = '';
            document.getElementById('date-range').value = 'month';
            document.getElementById('custom-date-range').style.display = 'none';
            document.getElementById('start-date').value = '';
            document.getElementById('end-date').value = '';
            document.getElementById('report-form').submit();
        });

        // Print report
        document.getElementById('print-report').addEventListener('click', function() {
            window.print();
        });

        // Generate report
        document.getElementById('generate-report').addEventListener('click', function() {
            const reportType = document.getElementById('report-type').value || 'appointments';
            alert(`Exporting ${reportType} report as PDF...`);
            // In a real application, this would generate and download a PDF
        });

        