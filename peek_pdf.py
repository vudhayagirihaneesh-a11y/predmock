import pdfplumber
import sys

def peek_pdf(pdf_path):
    print(f"--- Peeking into {pdf_path} ---")
    try:
        with pdfplumber.open(pdf_path) as pdf:
            page = pdf.pages[0]
            # Print plain text to see the structure
            print("TEXT:")
            print(page.extract_text())
            print("\nTABLES:")
            tables = page.extract_tables()
            for t in tables:
                for row in t[:5]:  # Just first 5 rows
                    print(row)
    except Exception as e:
        print("Error:", e)

if __name__ == "__main__":
    peek_pdf("TGEAPCET_2025_FINALPHASE_LASTRANKS.pdf")
