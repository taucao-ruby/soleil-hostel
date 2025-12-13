import './App.css'
import Booking from './components/Booking'
import Gallery from './components/Gallery'
import Review from './components/Review'
import Contact from './components/Contact'
import RoomList from './components/RoomList'
import { ToastContainer } from './utils/toast'

function App() {
  return (
    <div className="flex flex-col min-h-screen text-gray-900 transition-all duration-300 bg-gradient-to-br from-blue-100 via-yellow-50 to-pink-100">
      <ToastContainer />
      <header
        className="relative px-4 py-8 text-center shadow-lg bg-gradient-to-r from-blue-600 via-yellow-400 to-pink-500"
        role="banner"
      >
        <h1
          className="text-4xl font-extrabold tracking-wide text-white md:text-5xl drop-shadow-lg animate-fade-in"
          id="site-title"
        >
          Soleil Hostel
        </h1>
        <p
          className="mt-2 text-lg font-light md:text-xl animate-fade-in text-white/80"
          aria-label="Tagline: Your sunny stay in the heart of the city"
        >
          Your sunny stay in the heart of the city
        </p>
      </header>
      <main
        className="flex flex-col items-center justify-center flex-grow px-2 md:px-0"
        role="main"
        aria-label="Main content"
      >
        <div className="grid w-full max-w-6xl grid-cols-1 gap-10 py-10 md:grid-cols-2">
          <section
            className="col-span-1 animate-slide-up"
            aria-label="Booking and contact information"
          >
            <Booking />
            <div className="mt-10 animate-slide-up">
              <Contact />
            </div>
          </section>
          <section className="col-span-1 animate-slide-up" aria-label="Gallery and reviews">
            <Gallery />
            <div className="mt-10 animate-slide-up">
              <Review />
            </div>
          </section>
        </div>
        {/* Hiển thị danh sách phòng */}
        <RoomList />
      </main>
      <footer
        className="py-4 mt-auto text-center text-white bg-blue-600 shadow-inner animate-fade-in"
        role="contentinfo"
      >
        <p>
          <span aria-label={`Copyright ${new Date().getFullYear()} Soleil Hostel`}>
            &copy; {new Date().getFullYear()} Soleil Hostel. All rights reserved.
          </span>
        </p>
      </footer>
      <style>{`
        @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
        .animate-fade-in { animation: fade-in 1s ease; }
        @keyframes slide-up { from { transform: translateY(40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .animate-slide-up { animation: slide-up 0.8s cubic-bezier(.4,0,.2,1); }
      `}</style>
    </div>
  )
}

export default App
