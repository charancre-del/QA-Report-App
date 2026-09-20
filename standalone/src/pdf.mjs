import PDFDocument from 'pdfkit';
import { readFileSync } from 'node:fs';
import { problem } from './store.mjs';
import { readImage } from './media.mjs';

export async function renderReportPdf(detail, school, config, store, access) {
  let font;
  try { font = readFileSync(config.pdfFontPath); } catch { problem(503, 'pdf_font_unavailable'); }
  const doc = new PDFDocument({ size: 'LETTER', margin: 48, info: { Title: 'QA Inspection', Author: 'Chroma QA' }, bufferPages: true });
  const chunks = [];
  const completed = new Promise((resolve,reject) => { doc.on('data', c => chunks.push(c)); doc.on('end', () => resolve(Buffer.concat(chunks))); doc.on('error', reject); });
  // Observe rejection even if synchronous font/image rendering fails first.
  completed.catch(() => {});
  try {
    doc.font(font);
    const { report: r, checklist } = detail;
    doc.fontSize(22).fillColor('#153d4a').text('QA Inspection');
    doc.fontSize(16).text(school?.name ?? r.school_id);
    doc.fontSize(10).fillColor('#222222').text(`${r.inspection_date} | ${r.report_type} | ${r.status} | revision ${r.revision}`);
    if (r.status !== 'approved') doc.fillColor('#9a5900').text('DRAFT / NOT APPROVED').fillColor('#222222');
    doc.text(`Report ID: ${r.id}`).text(`Checklist: ${checklist.version}`).moveDown();
    const responses = new Map(detail.responses.map(x => [`${x.section_key}.${x.item_key}`, x]));
    for (const section of checklist.sections) {
      doc.fontSize(14).fillColor('#153d4a').text(section.name ?? section.key).fillColor('#222222').fontSize(10);
      for (const item of section.items) {
        const response = responses.get(`${section.key}.${item.key}`);
        doc.text(`${item.label}: ${response?.rating ?? 'Not answered'}`);
        if (response?.notes) doc.text(response.notes);
        doc.moveDown(.5);
      }
    }
    doc.fontSize(14).text('Closing notes').fontSize(10).text(r.closing_notes || 'None recorded').moveDown();
    doc.text(`Overall rating: ${r.overall_rating}`).moveDown();
    for (const photo of detail.photos) {
      const record = store.photo(r.id, photo.id, access), image = readImage(record, config.mediaDirectory);
      doc.addPage(); doc.fontSize(14).text('Photo evidence').fontSize(10).text(photo.caption || 'No caption').moveDown();
      doc.image(image, { fit: [500, 530], align: 'center', valign: 'top' });
    }
    const range = doc.bufferedPageRange();
    for (let i = range.start; i < range.start + range.count; i++) {
      doc.switchToPage(i); doc.fontSize(8).fillColor('#536773').text(`Private QA record · Page ${i + 1} of ${range.count}`, 48, 755, { lineBreak: false });
    }
    doc.end(); return await completed;
  } catch (error) { doc.destroy(); throw error; }
}
