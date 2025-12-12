import './App.css'
import Booking from './components/Booking'
import Gallery from './components/Gallery'
import Review from './components/Review'
import Contact from './components/Contact'
import RoomList from './components/RoomList'

function App() {
  return (
    <div className="min-h-screen flex flex-col transition-all duration-300 bg-gradient-to-br from-blue-100 via-yellow-50 to-pink-100 text-gray-900">
      <header className="bg-gradient-to-r from-blue-600 via-yellow-400 to-pink-500 shadow-lg py-8 px-4 text-center relative">
        <h1 className="text-4xl md:text-5xl font-extrabold drop-shadow-lg tracking-wide animate-fade-in text-white">
          Soleil Hostel
        </h1>
        <p className="mt-2 text-lg md:text-xl font-light animate-fade-in text-white/80">
          Your sunny stay in the heart of the city
        </p>
      </header>
      <main className="flex-grow flex flex-col items-center justify-center px-2 md:px-0">
        <div className="w-full max-w-6xl grid grid-cols-1 md:grid-cols-2 gap-10 py-10">
          <section className="col-span-1 animate-slide-up">
            <Booking />
            <div className="mt-10 animate-slide-up">
              <Contact />
            </div>
          </section>
          <section className="col-span-1 animate-slide-up">
            <Gallery />
            <div className="mt-10 animate-slide-up">
              <Review />
            </div>
          </section>
        </div>
        {/* Hiển thị danh sách phòng */}
        <RoomList />
      </main>
      <footer className="bg-blue-600 text-white text-center py-4 mt-auto shadow-inner animate-fade-in">
        &copy; {new Date().getFullYear()} Soleil Hostel. All rights reserved.
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
