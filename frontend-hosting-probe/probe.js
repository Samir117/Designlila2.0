const setCheck = (name, passed) => {
  const item = document.querySelector(`[data-check="${name}"]`);
  if (item) {
    item.dataset.status = passed ? "pass" : "fail";
    item.textContent += passed ? " — correcto" : " — revisar";
  }
};

const route = window.location.pathname;
document.querySelector("#current-route").textContent = route;
setCheck("html", true);
setCheck("javascript", true);
setCheck(
  "route",
  route === "/frontend-hosting-probe/" ||
    route === "/frontend-hosting-probe/prueba-ruta-seo/",
);

const stylesheet = [...document.styleSheets].some((sheet) => {
  try {
    return (
      sheet.href &&
      new URL(sheet.href).pathname === "/frontend-hosting-probe/probe.css"
    );
  } catch {
    return false;
  }
});
setCheck("asset", stylesheet);
