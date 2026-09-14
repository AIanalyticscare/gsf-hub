(function () {
	'use strict';

	function tableRows(table) {
		return Array.prototype.map.call(table.querySelectorAll('tr'), function (row) {
			return Array.prototype.map.call(row.querySelectorAll('th, td'), function (cell) {
				return cell.innerText.replace(/\s+/g, ' ').trim();
			});
		});
	}

	function fileName(table, extension) {
		return (table.getAttribute('data-gsf-export-name') || 'data-centre-table') + '.' + extension;
	}

	function download(blob, name) {
		var url = URL.createObjectURL(blob);
		var link = document.createElement('a');
		link.href = url;
		link.download = name;
		document.body.appendChild(link);
		link.click();
		link.remove();
		window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
	}

	function csv(table) {
		var content = tableRows(table).map(function (row) {
			return row.map(function (value) {
				return '"' + value.replace(/"/g, '""') + '"';
			}).join(',');
		}).join('\r\n');
		download(new Blob(['\ufeff' + content], { type: 'text/csv;charset=utf-8' }), fileName(table, 'csv'));
	}

	function excel(table) {
		var clone = table.cloneNode(true);
		var html = '<!doctype html><html><head><meta charset="utf-8"><style>table{border-collapse:collapse}th,td{border:1px solid #999;padding:8px;vertical-align:top}th{background:#eef5f4}</style></head><body>' + clone.outerHTML + '</body></html>';
		download(new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8' }), fileName(table, 'xls'));
	}

	function pdfEscape(value) {
		return value.replace(/[^\x20-\x7e]/g, '?').replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
	}

	function wrap(value, width) {
		var words = value.split(/\s+/), lines = [], line = '';
		words.forEach(function (word) {
			var next = line ? line + ' ' + word : word;
			if (next.length > width && line) { lines.push(line); line = word; } else { line = next; }
		});
		if (line) { lines.push(line); }
		return lines.length ? lines : [''];
	}

	function pdf(table) {
		var rows = tableRows(table), pages = [[]], pageWidth = 792, pageHeight = 612, margin = 28, y = pageHeight - margin, columnCount = Math.max.apply(null, rows.map(function (row) { return row.length; })), columnWidth = (pageWidth - margin * 2) / columnCount;
		function text(value, x, textY, size) {
			pages[pages.length - 1].push('BT /F1 ' + size + ' Tf ' + x.toFixed(1) + ' ' + textY.toFixed(1) + ' Td (' + pdfEscape(value) + ') Tj ET');
		}
		rows.forEach(function (row, rowIndex) {
			var cellLines = row.map(function (value) { return wrap(value, Math.max(10, Math.floor(columnWidth / 4.8))); });
			var height = Math.max.apply(null, cellLines.map(function (lines) { return lines.length; })) * 8 + 12;
			if (y - height < margin) { pages.push([]); y = pageHeight - margin; }
			cellLines.forEach(function (lines, column) {
				lines.forEach(function (line, lineIndex) { text(line, margin + column * columnWidth + 3, y - 8 * lineIndex, rowIndex === 0 ? 7 : 6); });
			});
			y -= height;
		});
		var objects = [], offsets = [], output = '%PDF-1.4\n', pageObjectStart = 4, contentObjectStart = pageObjectStart + pages.length;
		objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		objects[2] = '<< /Type /Pages /Kids [' + pages.map(function (_, index) { return (pageObjectStart + index) + ' 0 R'; }).join(' ') + '] /Count ' + pages.length + ' >>';
		objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
		pages.forEach(function (page, index) {
			var contentObject = contentObjectStart + index, stream = page.join('\n');
			objects[pageObjectStart + index] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /Resources << /Font << /F1 3 0 R >> >> /Contents ' + contentObject + ' 0 R >>';
			objects[contentObject] = '<< /Length ' + stream.length + ' >>\nstream\n' + stream + '\nendstream';
		});
		var objectCount = objects.length - 1;
		for (var i = 1; i <= objectCount; i++) { offsets[i] = output.length; output += i + ' 0 obj\n' + objects[i] + '\nendobj\n'; }
		var xref = output.length;
		output += 'xref\n0 ' + (objectCount + 1) + '\n0000000000 65535 f \n';
		for (var j = 1; j <= objectCount; j++) { output += ('0000000000' + offsets[j]).slice(-10) + ' 00000 n \n'; }
		output += 'trailer\n<< /Size ' + (objectCount + 1) + ' /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF';
		download(new Blob([output], { type: 'application/pdf' }), fileName(table, 'pdf'));
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-gsf-table-download]');
		if (!button) { return; }
		var table = document.getElementById(button.getAttribute('data-gsf-table-id'));
		if (!table) { return; }
		if (button.dataset.gsfTableDownload === 'csv') { csv(table); }
		if (button.dataset.gsfTableDownload === 'excel') { excel(table); }
		if (button.dataset.gsfTableDownload === 'pdf') { pdf(table); }
	});
}());
