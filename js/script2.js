// Counter Animation for Stats
document.addEventListener("DOMContentLoaded", () => {
  // Animate counters
  const counters = document.querySelectorAll("[data-count]")

  const animateCounter = (counter) => {
    const target = Number.parseInt(counter.getAttribute("data-count"))
    const duration = 2000
    const increment = target / (duration / 16)
    let current = 0

    const updateCounter = () => {
      current += increment
      if (current < target) {
        counter.textContent = Math.floor(current)
        requestAnimationFrame(updateCounter)
      } else {
        counter.textContent = target
      }
    }

    updateCounter()
  }

  // Intersection Observer for counter animation
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCounter(entry.target)
          observer.unobserve(entry.target)
        }
      })
    },
    { threshold: 0.5 },
  )

  counters.forEach((counter) => observer.observe(counter))

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault()
      const target = document.querySelector(this.getAttribute("href"))
      if (target) {
        const navHeight = document.querySelector(".navbar").offsetHeight
        const targetPosition = target.offsetTop - navHeight
        window.scrollTo({
          top: targetPosition,
          behavior: "smooth",
        })
      }
    })
  })

  // Navbar scroll effect
  const navbar = document.querySelector(".navbar")
  window.addEventListener("scroll", () => {
    if (window.scrollY > 50) {
      navbar.classList.add("shadow")
    } else {
      navbar.classList.remove("shadow")
    }
  })
})

// Authentication state management
const AuthManager = {
  isAuthenticated() {
    return localStorage.getItem("authToken") !== null
  },

  getUser() {
    const userStr = localStorage.getItem("user")
    return userStr ? JSON.parse(userStr) : null
  },

  setAuth(token, user) {
    localStorage.setItem("authToken", token)
    localStorage.setItem("user", JSON.stringify(user))
  },

  logout() {
    localStorage.removeItem("authToken")
    localStorage.removeItem("user")
    window.location.href = "index.html"
  },
}

// Show notification
function showNotification(message, type = "success") {
  const notification = document.createElement("div")
  notification.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3`
  notification.style.zIndex = "9999"
  notification.textContent = message
  document.body.appendChild(notification)

  setTimeout(() => {
    notification.remove()
  }, 3000)
}
