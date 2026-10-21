// Compares two extract.mjs outputs. Exit code 1 on any difference beyond 0.5 units in positions.
import {readFileSync} from 'node:fs';
const [a, b] = process.argv.slice(2).map((f) => JSON.parse(readFileSync(f, 'utf8')));
let problems = 0;
const tol = 0.5;
console.log(`Old ${a.version} vs new ${b.version}`);
for (const [name, da] of Object.entries(a.documents)) {
    const db = b.documents[name];
    const issues = [];
    if (!db) {
        issues.push('missing');
    } else {
        if (da.numPages !== db.numPages) {
            issues.push(`numPages ${da.numPages} != ${db.numPages}`);
        }
        if (JSON.stringify(da.outline) !== JSON.stringify(db.outline)) {
            issues.push('outline differs');
        }
        let textDiff = 0, boxDiff = 0, sizeDiff = 0, maxDelta = 0, items = 0, chars = 0;
        da.pages.forEach((pa, i) => {
            const pb = db.pages[i];
            if (pa.width !== pb.width || pa.height !== pb.height) {
                sizeDiff++;
            }
            if (pa.text !== pb.text) {
                textDiff++;
            }
            chars += pa.text.length;
            items += pa.boxes.length;
            if (pa.boxes.length !== pb.boxes.length) {
                boxDiff++;
                return;
            }
            pa.boxes.forEach((ba, j) => {
                const bb = pb.boxes[j];
                if (ba[0] !== bb[0]) {
                    boxDiff++;
                }
                for (let k = 1; k < 5; k++) {
                    maxDelta = Math.max(maxDelta, Math.abs(ba[k] - bb[k]));
                }
            });
        });
        if (sizeDiff) {
            issues.push(`${sizeDiff} page sizes differ`);
        }
        if (textDiff) {
            issues.push(`${textDiff} page texts differ`);
        }
        if (boxDiff) {
            issues.push(`${boxDiff} text item lists differ`);
        }
        if (maxDelta > tol) {
            issues.push(`max position delta ${maxDelta.toFixed(2)}`);
        }
        const outlineCount = (o) => o.reduce((n, e) => n + 1 + outlineCount(e.children), 0);
        console.log(`${issues.length ? 'DIFF' : 'SAME'}  ${name}: ${da.numPages} pages, ${outlineCount(da.outline)} outline entries, `
            + `${items} text items, ${chars} chars, max pos delta ${maxDelta.toFixed(3)}, `
            + `time ${da.ms} ms -> ${db.ms} ms${issues.length ? '  [' + issues.join('; ') + ']' : ''}`);
    }
    problems += issues.length;
}
process.exit(problems ? 1 : 0);
