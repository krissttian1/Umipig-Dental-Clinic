function openModal(id) {
    fetch('fetch_patient.php?id=' + id)
        .then(res => res.json())
        .then(data => {
            const formFields = document.getElementById('formFields');
            formFields.innerHTML = '';
            for (let key in data) {
                if (key === 'id' || key === 'created_at') continue;
                const label = key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase());
                let input;
                if (key.endsWith('Details') || key === 'reason') {
                    input = `<textarea readonly>${data[key] || ''}</textarea>`;
                } else if (["sex", "goodHealth", "underTreatment", "medication", "tobacco", "allergy", "oralIssues", "dentalComplications", "gumBleeding", "toothSensitivity", "grindingClenching", "dentalSurgery", "jawProblems", "pregnant", "birthControl"].includes(key)) {
                    input = `<select disabled><option>${data[key]}</option></select>`;
                } else {
                    input = `<input type="text" value="${data[key] || ''}" readonly>`;
                }
                formFields.innerHTML += `<label>${label}</label>${input}`;
            }
            document.getElementById('viewModal').style.display = 'block';
        });
}
function closeModal() {
    document.getElementById('viewModal').style.display = 'none';
}