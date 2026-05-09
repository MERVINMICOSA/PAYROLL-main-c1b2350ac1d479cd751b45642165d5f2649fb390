// report.js - Simplified version (all logic now in report.html)
console.log("Reports module loaded - All logic integrated in HTML");

window.switchReportType = function(type) {
    if (window.switchReportTypeHandler) {
        window.switchReportTypeHandler(type);
    }
};

window.refreshReport = function() {
    if (window.refreshReportHandler) {
        window.refreshReportHandler();
    }
};

window.exportToExcel = function() {
    if (window.exportExcelHandler) {
        window.exportExcelHandler();
    }
};

window.exportToPDF = function() {
    if (window.exportPDFHandler) {
        window.exportPDFHandler();
    }
};