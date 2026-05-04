document.addEventListener("DOMContentLoaded", function(){

const logoutBtn = document.getElementById("logoutBtn");
const logoutModal = document.getElementById("logoutModal");
const cancelLogout = document.getElementById("cancelLogout");
const confirmLogout = document.getElementById("confirmLogout");
const shippingCostInput = document.querySelector("input[name='shipping_cost']");
const logoutMessage = logoutModal ? logoutModal.querySelector("p") : null;
const defaultLogoutMessage = logoutMessage ? logoutMessage.textContent : "";

if(shippingCostInput){

shippingCostInput.addEventListener("wheel", function(e){
e.preventDefault();
}, { passive: false });

}

if(logoutBtn){

logoutBtn.addEventListener("click", function(e){
e.preventDefault();
if(logoutMessage){
logoutMessage.textContent = defaultLogoutMessage;
}
if(confirmLogout){
confirmLogout.disabled = false;
}
logoutModal.classList.add("show");
});

}

if(cancelLogout){

cancelLogout.addEventListener("click", function(){
if(logoutMessage){
logoutMessage.textContent = defaultLogoutMessage;
}
if(confirmLogout){
confirmLogout.disabled = false;
}
logoutModal.classList.remove("show");
});

}

if(confirmLogout){

confirmLogout.addEventListener("click", function(){
confirmLogout.disabled = true;

fetch("csrf_token.php")
.then(function(response){
return response.json();
})
.then(function(data){
if(!data || data.status !== "success" || !data.csrf_token){
throw new Error("Unable to initialize secure logout.");
}

return fetch("logout.php", {
method: "POST",
headers: {
"Content-Type": "application/x-www-form-urlencoded"
},
body: new URLSearchParams({
csrf_token: data.csrf_token
}).toString()
});
})
.then(function(response){
return response.json();
})
.then(function(data){
if(!data || data.status !== "success"){
throw new Error(data && data.message ? data.message : "Unable to logout right now.");
}

window.location.href = "login.php";
})
.catch(function(error){
if(logoutMessage){
logoutMessage.textContent = error.message || "Unable to logout right now. Please try again.";
}

confirmLogout.disabled = false;
});
});

}

});
