function editVaccine(id, name, quantity) {
    document.getElementById('vaccine_id').value = id;
    document.getElementById('vaccine_name').value = name;
    document.getElementById('vaccine_quantity').value = quantity;
    document.getElementById('editVaccineModal').classList.remove('hidden');
}

function editUser(id, name, email) {
    document.getElementById('user_id').value = id;
    document.getElementById('user_name').value = name;
    document.getElementById('user_email').value = email;
    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}