import { PDFJS } from "pdfjs-dist";

const pdfViewer = document.getElementById("pdf-viewer");
const highlightColor = "yellow"; // Default highlight color

function loadPDF(url) {
  PDFJS.getDocument(url).promise.then((pdf) => {
    // Render the PDF pages
    for (let i = 1; i <= pdf.numPages; i++) {
      pdf.getPage(i).then((page) => {
        const canvas = document.createElement("canvas");
        const context = canvas.getContext("2d");
        const viewport = page.getViewport({ scale: 1 });
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        const renderContext = {
          canvasContext: context,
          viewport: viewport,
        };
        page.render(renderContext).promise.then(() => {
          pdfViewer.appendChild(canvas);
        });
      });
    }
  });
}

function highlightText(selection) {
  const range = selection.getRangeAt(0);
  const span = document.createElement("span");
  span.style.backgroundColor = highlightColor;
  range.surroundContents(span);
}

document.addEventListener("mouseup", () => {
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    highlightText(selection);
  }
});

export { loadPDF };
