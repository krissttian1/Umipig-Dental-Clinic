const treatmentRecords = [];

function createDentalChart() {
    const upperRow = document.getElementById("upper-row");
    const lowerRow = document.getElementById("lower-row");
    const rightUpperRow = document.getElementById("right-upper-row");
    const leftUpperRow = document.getElementById("left-upper-row");

    for (let i = 1; i <= 16; i++) {
        upperRow.appendChild(createToothButton(i));
    }

    for (let i = 17; i <= 32; i++) {
        lowerRow.appendChild(createToothButton(i));
    }

    for (let i = 1; i <= 8; i++) {
        rightUpperRow.appendChild(createToothButton(i));
    }

    for (let i = 9; i <= 16; i++) {
        leftUpperRow.appendChild(createToothButton(i));
    }
}

function createToothButton(number) {
    const button = document.createElement("div");
    button.classList.add("tooth");
    button.textContent = number;
    button.addEventListener("click", () => {
        button.classList.toggle("selected");
    });
    return button;
}


function handleToothSelection(toothButton, toothNumber) {
    toothButton.classList.toggle("selected");

    const existingRecord = treatmentRecords.find(record => record.tooth === toothNumber);
    if (toothButton.classList.contains("selected")) {
        if (!existingRecord) {
            treatmentRecords.push({ tooth: toothNumber, date: new Date().toLocaleDateString(), diagnosis: "", treatment: "", charge: "", remarks: "" });
        }
    } else {
        const index = treatmentRecords.findIndex(record => record.tooth === toothNumber);
        if (index !== -1) treatmentRecords.splice(index, 1);
    }

    updateDentalTable();
}

function updateDentalTable() {
    const tableBody = document.getElementById("dental-table").getElementsByTagName("tbody")[0];
    tableBody.innerHTML = ""; 

    treatmentRecords.forEach(record => {
        const row = document.createElement("tr");
        row.innerHTML = `
            <td>${record.tooth}</td>
            <td>${record.date}</td>
            <td><input type="text" value="${record.diagnosis}" class="diagnosis-input" data-tooth="${record.tooth}"></td>
            <td><input type="text" value="${record.treatment}" class="treatment-input" data-tooth="${record.tooth}"></td>
            <td><input type="number" value="${record.charge}" class="charge-input" data-tooth="${record.tooth}"></td>
            <td><input type="text" value="${record.remarks}" class="remarks-input" data-tooth="${record.tooth}"></td>
            <td><button class="action-btn" onclick="deleteRecord(${record.tooth})">Delete</button></td>
        `;
        tableBody.appendChild(row);
    });

    
    const diagnosisInputs = document.querySelectorAll(".diagnosis-input");
    const treatmentInputs = document.querySelectorAll(".treatment-input");
    const chargeInputs = document.querySelectorAll(".charge-input");
    const remarksInputs = document.querySelectorAll(".remarks-input");

    diagnosisInputs.forEach(input => {
        input.addEventListener("input", (e) => updateRecord(e, "diagnosis"));
    });
    treatmentInputs.forEach(input => {
        input.addEventListener("input", (e) => updateRecord(e, "treatment"));
    });
    chargeInputs.forEach(input => {
        input.addEventListener("input", (e) => updateRecord(e, "charge"));
    });
    remarksInputs.forEach(input => {
        input.addEventListener("input", (e) => updateRecord(e, "remarks"));
    });
}


